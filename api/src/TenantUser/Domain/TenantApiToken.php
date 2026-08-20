<?php

namespace App\TenantUser\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * An issued bearer token for a TenantUser (KOZ-11). Rows live exclusively
 * inside a tenant schema, exactly like TenantUser itself — a token issued
 * on tenant A's schema simply does not exist when looked up while the
 * request's search_path is set to tenant B's schema (App\Tenancy\Infrastructure\TenantResolverListener),
 * regardless of what the caller presents. That structural separation, not
 * an explicit "does this token's tenant match the current tenant" check,
 * is what makes a token tenant-bound — the same approach KOZ-6/7 use for
 * tenant data isolation in general.
 *
 * Only a SHA-256 hash of the token is ever persisted, never the plaintext
 * — a stolen database row must not directly hand out a working token. A
 * cryptographic password hasher (bcrypt/argon2) is deliberately not used
 * here: those are salted and non-deterministic by design, so they cannot
 * be looked up by hash directly, whereas an opaque high-entropy random
 * token that is only ever compared once (not brute-forceable the way a
 * human-chosen password is) is safely looked up by a fast deterministic
 * hash instead.
 *
 * Sliding expiry (KOZ-15): issueFor() sets expiresAt EXPIRY_PERIOD out from
 * the issue moment, and every successful use renews it the same distance
 * out from that moment (renewExpiry(), called by
 * App\TenantUser\Infrastructure\Security\TenantApiTokenHandler on every
 * request it authenticates) — an actively used token effectively never
 * expires, while an abandoned one lapses on its own EXPIRY_PERIOD after the
 * last time it was actually used, with no separate refresh mechanism.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_user_api_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_user_api_tokens_token_hash', columns: ['token_hash'])]
class TenantApiToken
{
    /**
     * The sliding-expiry window (KOZ-15): how long an issued token stays
     * valid after it was issued, or, on every subsequent successful use,
     * after that use — see renewExpiry(). 30 days was chosen as a generous
     * default for an active user (so a normal usage cadence never forces a
     * re-login) while still ensuring an abandoned token doesn't stay valid
     * indefinitely.
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

    /**
     * Sliding expiry (KOZ-15): pushes expiresAt EXPIRY_PERIOD out from
     * $now. Called on every successful token validation, not just at issue
     * time — see App\TenantUser\Infrastructure\Security\TenantApiTokenHandler.
     */
    public function renewExpiry(\DateTimeImmutable $now): void
    {
        $this->expiresAt = self::expiryFrom($now);
    }

    private static function expiryFrom(\DateTimeImmutable $moment): \DateTimeImmutable
    {
        return $moment->add(new \DateInterval(self::EXPIRY_PERIOD));
    }
}
