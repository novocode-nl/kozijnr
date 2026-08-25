<?php

namespace App\Tests\ProfilePhoto\Infrastructure;

use App\ProfilePhoto\Domain\ProfilePhoto;
use App\ProfilePhoto\Domain\ProfilePhotoRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineProfilePhotoRepositoryTest extends KernelTestCase
{
    private ProfilePhotoRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.profile_photos');

        $this->repository = self::getContainer()->get(ProfilePhotoRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('SET search_path TO public');
        $connection->executeStatement('DELETE FROM public.profile_photos');

        parent::tearDown();
    }

    public function testFindByOwnerIdReturnsNullWhenNoneExists(): void
    {
        self::assertNull($this->repository->findByOwnerId(999));
    }

    public function testAddPersistsAndFindByOwnerIdRetrievesIt(): void
    {
        $photo = ProfilePhoto::uploadedFor(7, 'profile-photos/7/abc.jpg', 'image/jpeg', 100, 'me.jpg');

        $this->repository->add($photo);

        $found = $this->repository->findByOwnerId(7);

        self::assertNotNull($found);
        self::assertSame(7, $found->getOwnerId());
        self::assertSame('profile-photos/7/abc.jpg', $found->getStorageKey());
        self::assertSame('image/jpeg', $found->getMimeType());
        self::assertSame(100, $found->getSizeInBytes());
        self::assertSame('me.jpg', $found->getOriginalFilename());
    }

    public function testRemoveDeletesTheRow(): void
    {
        $photo = ProfilePhoto::uploadedFor(8, 'profile-photos/8/abc.jpg', 'image/jpeg', 100, 'me.jpg');
        $this->repository->add($photo);

        $this->repository->remove($photo);

        self::assertNull($this->repository->findByOwnerId(8));
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }
}
