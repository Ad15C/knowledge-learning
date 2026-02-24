<?php

namespace App\Tests\Repository;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testPersistAndFindLesson(): void
    {
        $theme = new Theme();
        $theme->setName('Theme');
        $this->em->persist($theme);

        $cursus = new Cursus();
        $cursus->setName('Cursus')
            ->setPrice(100)
            ->setTheme($theme);
        $this->em->persist($cursus);

        $lesson = new Lesson();
        $lesson->setTitle('Leçon X')
            ->setPrice(9.99)
            ->setCursus($cursus);

        $this->em->persist($lesson);
        $this->em->flush();
        $this->em->clear();

        $repo = $this->em->getRepository(Lesson::class);
        $found = $repo->findOneBy(['title' => 'Leçon X']);

        self::assertNotNull($found);
        self::assertSame('Leçon X', $found->getTitle());
        self::assertSame(9.99, $found->getPrice());
        self::assertSame('Cursus', $found->getCursus()?->getName());
        self::assertSame('Theme', $found->getCursus()?->getTheme()?->getName());
    }
}