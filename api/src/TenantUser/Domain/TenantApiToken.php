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
 */
#[ORM\Entity]
#[ORM\Table(name: 'tenant_user_api_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_user_api_tokens_token_hash', columns: ['token_hash'])]
class TenantApiToken
{
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

    /** Never persisted — only held transiently so the caller can return it once. */
    private string $plainTextToken;

    private function __construct(TenantUser $tenantUser, string $plainTextToken, string $tokenHash, \DateTimeImmutable $createdAt)
    {
        $this->tenantUser = $tenantUser;
        $this->plainTextToken = $plainTextToken;
        $this->tokenHash = $tokenHash;
        $this->createdAt = $createdAt;
    }

    public static function issueFor(TenantUser $tenantUser): self
    {
        $plainTextToken = bin2hex(random_bytes(32));

        return new self($tenantUser, $plainTextToken, self::hashFor($plainTextToken), new \DateTimeImmutable());
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
}
