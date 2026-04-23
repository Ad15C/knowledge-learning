<?php

namespace App\Command;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Repository\CursusRepository;
use App\Repository\LessonRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:rebuild-slugs',
    description: 'Régénère proprement tous les slugs Theme, Cursus et Lesson'
)]
class RebuildSlugsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private ThemeRepository $themeRepository,
        private CursusRepository $cursusRepository,
        private LessonRepository $lessonRepository
    ) {
        parent::__construct();
    }

    private function buildSafeSlug(string $text): string
    {
        $slug = strtolower($this->slugger->slug($text)->toString());
        $slug = str_replace(['’', "'", '`'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'item';
    }

    private function uniqueSlug(string $baseSlug, object $entity, callable $finderBySlug): string
    {
        $slug = $baseSlug;
        $i = 1;

        $existing = $finderBySlug($slug);

        while ($existing !== null && $existing->getId() !== $entity->getId()) {
            $slug = $baseSlug . '-' . $i;
            $i++;
            $existing = $finderBySlug($slug);
        }

        return $slug;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Régénération des slugs...');

        foreach ($this->themeRepository->findAll() as $theme) {
            $baseSlug = $this->buildSafeSlug($theme->getName() ?? 'theme');
            $slug = $this->uniqueSlug(
                $baseSlug,
                $theme,
                fn (string $slug) => $this->themeRepository->findOneBy(['slug' => $slug])
            );
            $theme->setSlug($slug);
        }

        foreach ($this->cursusRepository->findAll() as $cursus) {
            $baseSlug = $this->buildSafeSlug($cursus->getName() ?? 'cursus');
            $slug = $this->uniqueSlug(
                $baseSlug,
                $cursus,
                fn (string $slug) => $this->cursusRepository->findOneBy(['slug' => $slug])
            );
            $cursus->setSlug($slug);
        }

        foreach ($this->lessonRepository->findAll() as $lesson) {
            $baseSlug = $this->buildSafeSlug($lesson->getTitle() ?? 'lesson');
            $slug = $this->uniqueSlug(
                $baseSlug,
                $lesson,
                fn (string $slug) => $this->lessonRepository->findOneBy(['slug' => $slug])
            );
            $lesson->setSlug($slug);
        }

        $this->em->flush();

        $output->writeln('Slugs régénérés avec succès.');

        return Command::SUCCESS;
    }
}