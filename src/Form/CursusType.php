<?php

namespace App\Form;

use App\Entity\Cursus;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CursusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('theme', EntityType::class, [
                'class' => Theme::class,
                'choice_label' => 'name',
                'label' => 'Thème',
                'placeholder' => '— Choisir un thème —',
                'query_builder' => function (ThemeRepository $tr) use ($options) {
                    $qb = $tr->createQueryBuilder('t')
                        ->distinct()
                        ->orderBy('t.name', 'ASC');

                    $cursus = $options['data'] ?? null;

                    if ($cursus instanceof Cursus && $cursus->getTheme()) {
                        $qb->andWhere(
                            $qb->expr()->orX(
                                't.isActive = true',
                                't.id = :currentThemeId'
                            )
                        )
                        ->setParameter('currentThemeId', $cursus->getTheme()->getId());
                    } else {
                        $qb->andWhere('t.isActive = true');
                    }

                    return $qb;
                },
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('image', TextType::class, [
                'label' => 'Image (chemin/URL)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Cursus::class,
        ]);
    }
}