<?php

namespace App\Form;

use App\Entity\Lesson;
use App\Entity\Cursus;
use App\Repository\CursusRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'empty_data' => '',
            ])
            ->add('cursus', EntityType::class, [
                'class' => Cursus::class,
                'choice_label' => 'name',
                'label' => 'Cursus',
                'placeholder' => '— Choisir un cursus —',
                'query_builder' => fn (CursusRepository $cr) => $cr->createQueryBuilder('c')
                    ->andWhere('c.isActive = true')
                    ->orderBy('c.name', 'ASC'),
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'required' => false,
            ])
            ->add('fiche', TextareaType::class, [
                'label' => 'Fiche',
                'required' => false,
            ])
            ->add('videoUrl', TextType::class, [
                'label' => 'URL vidéo',
                'required' => false,
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (chemin/URL)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
        ]);
    }
}