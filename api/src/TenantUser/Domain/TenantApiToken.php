<?php

namespace App\TenantUser\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * An issued bearer token for a TenantUser. Rows live exclusively inside a
 * tenant schema, exactly like TenantUser itself — a token issued on tenant
 * A's schema simply does not exist when looked up under tenant B's
 * search_path. That structural separation is what makes a token
 * tenant-bound, not an explicit tenant-match check.
 *
 * Only a SHA-256 hash of the token is ever persisted, never the plaintext.
 * A cryptographic password hasher (bcrypt/argon2) is deliberately not used
 * here: those are salted/non-deterministic and can't be looked up by hash
 * directly, whereas a high-entropy random token is safe to look up by a
 * fast deterministic hash.
 *
 * Sliding expiry: issueFor() sets expiresAt EXPIRY_PERIOD out from issue
 * time, and every successful use renews it the same distance out
 * (renewExpiry()) — an actively used token effectively never expires,
 * while an abandoned one lapses EXPIRY_PERIOD after its last use.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_user_api_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_user_api_tokens_token_hash', columns: ['token_hash'])]
class TenantApiToken
{
    /**
     * The sliding-expiry window: how long an issued token stays valid after
     * issue, or after each subsequent use — see renewExpiry(). 30 days is a
     * generous default so normal usage never forces a re-login, while an
     * abandoned token still lapses eventually.
     */
    private const EXPIRY_PERIOD = 'P30D';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TenantUser::class)]
    #[ORM\JoinColumn(name: 'tenant_user_id', nullable: false, onDelete: 'CASCADE')]
    private TenantUser $tenantUser;

    #[ORM\Column(name: 'token_hash', type: 'string', length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    /** Never persisted — only held transiently so the caller can return it once. */
    private string $plainTextToken;

    private function __construct(
        TenantUser $tenantUser,
        string $plainTextToken,
        string $tokenHash,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->tenantUser = $tenantUser;
        $this->plainTextToken = $plainTextToken;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
    }

    public static function issueFor(TenantUser $tenantUser): self
    {
        $plainTextToken = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();

        return new self($tenantUser, $plainTextToken, self::hashFor($plainTextToken), $now, self::expiryFrom($now));
    }

    public static function hashFor(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantUser(): TenantUser
    {
        return $this->tenantUser;
    }

    /**
     * Only meaningful right after issueFor() created this instance — never
     * populated on an instance loaded back from storage, since only the
     * hash is ever persisted.
     */
    public function getPlainTextToken(): string
    {
        return $this->plainTextToken;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now > $this->expiresAt;
    }

    /** Sliding expiry: pushes expiresAt EXPIRY_PERIOD out from $now. */
    public function renewExpiry(\DateTimeImmutable $now): void
    {
        $this->expiresAt = self::expiryFrom($now);
    }

    private static function expiryFrom(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return $moment->add(new \DateInterval(self::EXPIRY_PERIOD));
    }
}
