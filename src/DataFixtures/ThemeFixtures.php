<?php

namespace App\DataFixtures;

use App\Entity\Theme;
use App\Entity\Cursus;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ThemeFixtures extends Fixture
{
    public const THEME_MUSIQUE_REF = 'theme_musique';
    public const CURSUS_GUITARE_REF = 'cursus_guitare';

    public const LESSON_GUITAR_1_REF = 'lesson_guitar_1';
    public const LESSON_GUITAR_2_REF = 'lesson_guitar_2';

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // === Thème Musique ===
        $theme = new Theme();
        $theme->setName('Musique')
            ->setDescription('Apprenez la guitare et le piano')
            ->setImage('images/themes/musique/cours_musique.jpg');
        $manager->persist($theme);
        $this->addReference(self::THEME_MUSIQUE_REF, $theme);

        // Cursus guitare
        $cursusGuitare = new Cursus();
        $cursusGuitare->setName('Cursus d’initiation à la guitare')
            ->setPrice(50)
            ->setTheme($theme)
            ->setImage('images/themes/musique/cours_guitare.jpg');
        $manager->persist($cursusGuitare);
        $this->addReference(self::CURSUS_GUITARE_REF, $cursusGuitare);

        // Leçons guitare
        $lesson1 = new Lesson();
        $lesson1->setTitle('Découverte de l’instrument')
            ->setPrice(26)
            ->setCursus($cursusGuitare)
            ->setImage('images/themes/musique/cours_guitare.jpg')
            ->setFiche($this->generateArticles($faker))
            ->setVideoUrl('https://youtu.be/example1');
        $manager->persist($lesson1);
        $this->addReference(self::LESSON_GUITAR_1_REF, $lesson1);

        $lesson2 = new Lesson();
        $lesson2->setTitle('Les accords et les gammes')
            ->setPrice(26)
            ->setCursus($cursusGuitare)
            ->setImage('images/themes/musique/accords_et_gammes.jpg')
            ->setFiche($this->generateArticles($faker))
            ->setVideoUrl('https://youtu.be/example2');
        $manager->persist($lesson2);
        $this->addReference(self::LESSON_GUITAR_2_REF, $lesson2);

        // Cursus piano
        $cursusPiano = new Cursus();
        $cursusPiano->setName('Cursus d’initiation au piano')
            ->setPrice(50)
            ->setTheme($theme)
            ->setImage('images/themes/musique/cours_piano.jpg');
        $manager->persist($cursusPiano);

        // Leçons piano
        $lesson3 = new Lesson();
        $lesson3->setTitle('Découverte du piano')
            ->setPrice(26)
            ->setCursus($cursusPiano)
            ->setImage('images/themes/musique/cours_piano.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson3);

        $lesson4 = new Lesson();
        $lesson4->setTitle('Les accords et gammes au piano')
            ->setPrice(26)
            ->setCursus($cursusPiano)
            ->setImage('images/themes/musique/accords_et_gammes.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson4);

        // === Thème Informatique ===
        $themeInfo = new Theme();
        $themeInfo->setName('Informatique')
            ->setDescription('Initiez-vous au développement web')
            ->setImage('images/themes/informatique/cours_developpement_web.jpg');
        $manager->persist($themeInfo);

        $cursusWeb = new Cursus();
        $cursusWeb->setName('Cursus d’initiation au développement web')
            ->setPrice(60)
            ->setTheme($themeInfo)
            ->setImage('images/themes/informatique/cours_developpement_web.jpg');
        $manager->persist($cursusWeb);

        $lesson5 = new Lesson();
        $lesson5->setTitle('Les langages HTML et CSS')
            ->setPrice(32)
            ->setCursus($cursusWeb)
            ->setImage('images/themes/informatique/les_langages_html_css.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson5);

        $lesson6 = new Lesson();
        $lesson6->setTitle('Dynamiser votre site avec JavaScript')
            ->setPrice(32)
            ->setCursus($cursusWeb)
            ->setImage('images/themes/informatique/dynamiser_site_javascript.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson6);

        // === Thème Jardinage ===
        $themeJardin = new Theme();
        $themeJardin->setName('Jardinage')
            ->setDescription('Cursus d’initiation au jardinage')
            ->setImage('images/themes/jardinage/initiation_jardinage.jpg');
        $manager->persist($themeJardin);

        $cursusJardin = new Cursus();
        $cursusJardin->setName('Cursus d’initiation au jardinage')
            ->setPrice(30)
            ->setTheme($themeJardin)
            ->setImage('images/themes/jardinage/initiation_jardinage.jpg');
        $manager->persist($cursusJardin);

        $lesson7 = new Lesson();
        $lesson7->setTitle('Les outils du jardinier')
            ->setPrice(16)
            ->setCursus($cursusJardin)
            ->setImage('images/themes/jardinage/outils_du_jardinier.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson7);

        $lesson8 = new Lesson();
        $lesson8->setTitle('Jardiner avec la lune')
            ->setPrice(16)
            ->setCursus($cursusJardin)
            ->setImage('images/themes/jardinage/jardiner_avec_la_lune.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson8);

        // === Thème Cuisine ===
        $themeCuisine = new Theme();
        $themeCuisine->setName('Cuisine')
            ->setDescription('Cursus d’initiation à la cuisine et dressage culinaire')
            ->setImage('images/themes/cuisine/cours_cuisine.jpg');
        $manager->persist($themeCuisine);

        $cursusCuisine1 = new Cursus();
        $cursusCuisine1->setName('Cursus d’initiation à la cuisine')
            ->setPrice(44)
            ->setTheme($themeCuisine)
            ->setImage('images/themes/cuisine/cours_cuisine.jpg');
        $manager->persist($cursusCuisine1);

        $lesson9 = new Lesson();
        $lesson9->setTitle('Les modes de cuisson')
            ->setPrice(23)
            ->setCursus($cursusCuisine1)
            ->setImage('images/themes/cuisine/modes_cuisson.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson9);

        $lesson10 = new Lesson();
        $lesson10->setTitle('Les saveurs')
            ->setPrice(23)
            ->setCursus($cursusCuisine1)
            ->setImage('images/themes/cuisine/saveurs.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson10);

        $cursusCuisine2 = new Cursus();
        $cursusCuisine2->setName('Cursus d’initiation à l’art du dressage culinaire')
            ->setPrice(48)
            ->setTheme($themeCuisine)
            ->setImage('images/themes/cuisine/dressage_culinaire.jpg');
        $manager->persist($cursusCuisine2);

        $lesson11 = new Lesson();
        $lesson11->setTitle('Mettre en œuvre le style dans l’assiette')
            ->setPrice(26)
            ->setCursus($cursusCuisine2)
            ->setImage('images/themes/cuisine/style_dans_assiette.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson11);

        $lesson12 = new Lesson();
        $lesson12->setTitle('Harmoniser un repas à quatre plats')
            ->setPrice(26)
            ->setCursus($cursusCuisine2)
            ->setImage('images/themes/cuisine/quatre_plats.jpg')
            ->setFiche($this->generateArticles($faker));
        $manager->persist($lesson12);

        $manager->flush();
    }

    private function generateArticles($faker, int $nbArticles = 3): string
    {
        $articles = [];

        for ($i = 0; $i < $nbArticles; $i++) {
            $paragraphs = $faker->paragraphs(2, true);
            $articles[] = '<p>' . str_replace("\n", '</p><p>', $paragraphs) . '</p>';
        }

        return implode("\n<br><br>\n", $articles);
    }
}