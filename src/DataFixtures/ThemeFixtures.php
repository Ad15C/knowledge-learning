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

    private function buildSafeSlug(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['à', 'á', 'â', 'ä'], 'a', $text);
        $text = str_replace(['ç'], 'c', $text);
        $text = str_replace(['é', 'è', 'ê', 'ë'], 'e', $text);
        $text = str_replace(['î', 'ï'], 'i', $text);
        $text = str_replace(['ô', 'ö'], 'o', $text);
        $text = str_replace(['ù', 'û', 'ü'], 'u', $text);
        $text = str_replace(['œ'], 'oe', $text);
        $text = str_replace(['æ'], 'ae', $text);
        $text = str_replace(['’', "'", '`'], '-', $text);
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        $text = trim($text, '-');

        return $text !== '' ? $text : 'item';
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // === Thème Musique ===
        $theme = new Theme();
        $theme->setName('Musique');
        $theme->setSlug($this->buildSafeSlug($theme->getName() ?? 'theme'));
        $theme->setDescription('Apprenez la guitare et le piano');
        $theme->setImage('images/themes/musique/cours_musique.jpg');
        $theme->setAlt('Instruments de musique dans un studio avec guitares et clavier');
        $manager->persist($theme);
        $this->addReference(self::THEME_MUSIQUE_REF, $theme);

        $cursusGuitare = new Cursus();
        $cursusGuitare->setName("Cursus d’initiation à la guitare");
        $cursusGuitare->setSlug($this->buildSafeSlug($cursusGuitare->getName() ?? 'cursus'));
        $cursusGuitare->setPrice(50);
        $cursusGuitare->setTheme($theme);
        $cursusGuitare->setImage('images/themes/musique/cours_guitare.jpg');
        $cursusGuitare->setAlt("Gros plan d'une personne jouant de la guitare acoustique");
        $manager->persist($cursusGuitare);
        $this->addReference(self::CURSUS_GUITARE_REF, $cursusGuitare);

        $lesson1 = new Lesson();
        $lesson1->setTitle("Découverte de l’instrument");
        $lesson1->setSlug($this->buildSafeSlug($lesson1->getTitle() ?? 'lesson'));
        $lesson1->setPrice(26);
        $lesson1->setCursus($cursusGuitare);
        $lesson1->setImage('images/themes/musique/cours_guitare.jpg');
        $lesson1->setAlt("Personne jouant de la guitare acoustique en gros plan");
        $lesson1->setFiche($this->generateArticles($faker));
        $lesson1->setVideoUrl('https://youtu.be/example1');
        $manager->persist($lesson1);
        $this->addReference(self::LESSON_GUITAR_1_REF, $lesson1);

        $lesson2 = new Lesson();
        $lesson2->setTitle('Les accords et les gammes de la guitare');
        $lesson2->setSlug($this->buildSafeSlug($lesson2->getTitle() ?? 'lesson'));
        $lesson2->setPrice(26);
        $lesson2->setCursus($cursusGuitare);
        $lesson2->setImage('images/themes/musique/accords_et_gammes.jpg');
        $lesson2->setAlt('Partition de musique avec notes et accords en gros plan');
        $lesson2->setFiche($this->generateArticles($faker));
        $lesson2->setVideoUrl('https://youtu.be/example2');
        $manager->persist($lesson2);
        $this->addReference(self::LESSON_GUITAR_2_REF, $lesson2);

        $cursusPiano = new Cursus();
        $cursusPiano->setName("Cursus d’initiation au piano");
        $cursusPiano->setSlug($this->buildSafeSlug($cursusPiano->getName() ?? 'cursus'));
        $cursusPiano->setPrice(50);
        $cursusPiano->setTheme($theme);
        $cursusPiano->setImage('images/themes/musique/cours_piano.jpg');
        $cursusPiano->setAlt('Clavier de piano vu en perspective avec touches noires et blanches');
        $manager->persist($cursusPiano);

        $lesson3 = new Lesson();
        $lesson3->setTitle('Découverte du piano');
        $lesson3->setSlug($this->buildSafeSlug($lesson3->getTitle() ?? 'lesson'));
        $lesson3->setPrice(26);
        $lesson3->setCursus($cursusPiano);
        $lesson3->setImage('images/themes/musique/cours_piano.jpg');
        $lesson3->setAlt("Vue perspective d'un clavier de piano");
        $lesson3->setFiche($this->generateArticles($faker));
        $manager->persist($lesson3);

        $lesson4 = new Lesson();
        $lesson4->setTitle('Les accords et gammes au piano');
        $lesson4->setSlug($this->buildSafeSlug($lesson4->getTitle() ?? 'lesson'));
        $lesson4->setPrice(26);
        $lesson4->setCursus($cursusPiano);
        $lesson4->setImage('images/themes/musique/accords_et_gammes.jpg');
        $lesson4->setAlt('Gros plan sur une partition de musique avec des notes et accords');
        $lesson4->setFiche($this->generateArticles($faker));
        $manager->persist($lesson4);

        // === Thème Informatique ===
        $themeInformatique = new Theme();
        $themeInformatique->setName('Informatique');
        $themeInformatique->setSlug($this->buildSafeSlug($themeInformatique->getName() ?? 'theme'));
        $themeInformatique->setDescription('Initiez-vous au développement web');
        $themeInformatique->setImage('images/themes/informatique/cours_developpement_web.jpg');
        $themeInformatique->setAlt("Écran d'ordinateur avec code visible à travers des lunettes");
        $manager->persist($themeInformatique);

        $cursusWeb = new Cursus();
        $cursusWeb->setName("Cursus d’initiation au développement web");
        $cursusWeb->setSlug($this->buildSafeSlug($cursusWeb->getName() ?? 'cursus'));
        $cursusWeb->setPrice(60);
        $cursusWeb->setTheme($themeInformatique);
        $cursusWeb->setImage('images/themes/informatique/cours_developpement_web.jpg');
        $cursusWeb->setAlt("Aperçu de code sur un écran d'ordinateur");
        $manager->persist($cursusWeb);

        $lesson5 = new Lesson();
        $lesson5->setTitle('Les langages HTML et CSS');
        $lesson5->setSlug($this->buildSafeSlug($lesson5->getTitle() ?? 'lesson'));
        $lesson5->setPrice(32);
        $lesson5->setCursus($cursusWeb);
        $lesson5->setImage('images/themes/informatique/les_langages_html_css.jpg');
        $lesson5->setAlt('Code HTML et CSS affiché sur un écran');
        $lesson5->setFiche($this->generateArticles($faker));
        $manager->persist($lesson5);

        $lesson6 = new Lesson();
        $lesson6->setTitle('Dynamiser votre site avec JavaScript');
        $lesson6->setSlug($this->buildSafeSlug($lesson6->getTitle() ?? 'lesson'));
        $lesson6->setPrice(32);
        $lesson6->setCursus($cursusWeb);
        $lesson6->setImage('images/themes/informatique/dynamiser_site_javascript.jpg');
        $lesson6->setAlt('Interface de développement avec du code JavaScript affiché');
        $lesson6->setFiche($this->generateArticles($faker));
        $manager->persist($lesson6);

        // === Thème Jardinage ===
        $themeJardin = new Theme();
        $themeJardin->setName('Jardinage');
        $themeJardin->setSlug($this->buildSafeSlug($themeJardin->getName() ?? 'theme'));
        $themeJardin->setDescription('Cursus d’initiation au jardinage');
        $themeJardin->setImage('images/themes/jardinage/initiation_jardinage.jpg');
        $themeJardin->setAlt("Arrosoir versant de l'eau sur des plantes dans un jardin");
        $manager->persist($themeJardin);

        $cursusJardin = new Cursus();
        $cursusJardin->setName("Cursus d’initiation au jardinage");
        $cursusJardin->setSlug($this->buildSafeSlug($cursusJardin->getName() ?? 'cursus'));
        $cursusJardin->setPrice(30);
        $cursusJardin->setTheme($themeJardin);
        $cursusJardin->setImage('images/themes/jardinage/initiation_jardinage.jpg');
        $cursusJardin->setAlt("Photo montrant l'arrosage des plantes dans un jardin");
        $manager->persist($cursusJardin);

        $lesson7 = new Lesson();
        $lesson7->setTitle('Les outils du jardinier');
        $lesson7->setSlug($this->buildSafeSlug($lesson7->getTitle() ?? 'lesson'));
        $lesson7->setPrice(16);
        $lesson7->setCursus($cursusJardin);
        $lesson7->setImage('images/themes/jardinage/outils_du_jardinier.jpg');
        $lesson7->setAlt('Outils de jardinage posés sur une surface en bois');
        $lesson7->setFiche($this->generateArticles($faker));
        $manager->persist($lesson7);

        $lesson8 = new Lesson();
        $lesson8->setTitle('Jardiner avec la lune');
        $lesson8->setSlug($this->buildSafeSlug($lesson8->getTitle() ?? 'lesson'));
        $lesson8->setPrice(16);
        $lesson8->setCursus($cursusJardin);
        $lesson8->setImage('images/themes/jardinage/jardiner_avec_la_lune.jpg');
        $lesson8->setAlt('Pleine lune visible dans le ciel nocturne à travers des arbres');
        $lesson8->setFiche($this->generateArticles($faker));
        $manager->persist($lesson8);

        // === Thème Cuisine ===
        $themeCuisine = new Theme();
        $themeCuisine->setName('Cuisine');
        $themeCuisine->setSlug($this->buildSafeSlug($themeCuisine->getName() ?? 'theme'));
        $themeCuisine->setDescription("Cursus d’initiation à la cuisine et dressage culinaire");
        $themeCuisine->setImage('images/themes/cuisine/cours_cuisine.jpg');
        $themeCuisine->setAlt('Deux personnes préparant un plat ensemble dans une cuisine');
        $manager->persist($themeCuisine);

        $cursusCuisine1 = new Cursus();
        $cursusCuisine1->setName('Cursus d’initiation à la cuisine');
        $cursusCuisine1->setSlug($this->buildSafeSlug($cursusCuisine1->getName() ?? 'cursus'));
        $cursusCuisine1->setPrice(44);
        $cursusCuisine1->setTheme($themeCuisine);
        $cursusCuisine1->setImage('images/themes/cuisine/cours_cuisine.jpg');
        $cursusCuisine1->setAlt('Photo de deux personnes préparant un plat ensemble dans une cuisine');
        $manager->persist($cursusCuisine1);

        $lesson9 = new Lesson();
        $lesson9->setTitle('Les modes de cuisson');
        $lesson9->setSlug($this->buildSafeSlug($lesson9->getTitle() ?? 'lesson'));
        $lesson9->setPrice(23);
        $lesson9->setCursus($cursusCuisine1);
        $lesson9->setImage('images/themes/cuisine/modes_cuisson.jpg');
        $lesson9->setAlt('Poêles sur une cuisinière avec aliments en train de cuire');
        $lesson9->setFiche($this->generateArticles($faker));
        $manager->persist($lesson9);

        $lesson10 = new Lesson();
        $lesson10->setTitle('Les saveurs');
        $lesson10->setSlug($this->buildSafeSlug($lesson10->getTitle() ?? 'lesson'));
        $lesson10->setPrice(23);
        $lesson10->setCursus($cursusCuisine1);
        $lesson10->setImage('images/themes/cuisine/saveurs.jpg');
        $lesson10->setAlt('Épices variées présentées dans de petits bols');
        $lesson10->setFiche($this->generateArticles($faker));
        $manager->persist($lesson10);

        $cursusCuisine2 = new Cursus();
        $cursusCuisine2->setName("Cursus d’initiation à l’art du dressage culinaire");
        $cursusCuisine2->setSlug($this->buildSafeSlug($cursusCuisine2->getName() ?? 'cursus'));
        $cursusCuisine2->setPrice(48);
        $cursusCuisine2->setTheme($themeCuisine);
        $cursusCuisine2->setImage('images/themes/cuisine/dressage_culinaire.jpg');
        $cursusCuisine2->setAlt('Table de restaurant dressée avec des assiettes et bougies');
        $manager->persist($cursusCuisine2);

        $lesson11 = new Lesson();
        $lesson11->setTitle("Mettre en œuvre le style dans l’assiette");
        $lesson11->setSlug($this->buildSafeSlug($lesson11->getTitle() ?? 'lesson'));
        $lesson11->setPrice(26);
        $lesson11->setCursus($cursusCuisine2);
        $lesson11->setImage('images/themes/cuisine/style_dans_assiette.jpg');
        $lesson11->setAlt('Chef dressant des assiettes avec soin en cuisine');
        $lesson11->setFiche($this->generateArticles($faker));
        $manager->persist($lesson11);

        $lesson12 = new Lesson();
        $lesson12->setTitle('Harmoniser un repas à quatre plats');
        $lesson12->setSlug($this->buildSafeSlug($lesson12->getTitle() ?? 'lesson'));
        $lesson12->setPrice(26);
        $lesson12->setCursus($cursusCuisine2);
        $lesson12->setImage('images/themes/cuisine/quatre_plats.jpg');
        $lesson12->setAlt('Assortiment de plats avec vin, dessert et plats salés');
        $lesson12->setFiche($this->generateArticles($faker));
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