<?php

namespace App\Form;

use App\Entity\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactMessageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', TextType::class, ['label' => 'Votre nom'])
            ->add('email', EmailType::class, ['label' => 'Votre e-mail'])
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet',
                'choices' => [
                    'Question sur un thème' => 'theme',
                    'Question sur un cursus' => 'cursus',
                    'Question sur une leçon' => 'lesson',
                    'Question sur le paiement' => 'payment',
                    'Question sur la validation du cours' => 'validation',
                    'Question sur la certification' => 'certification',
                    'Question sur l’inscription' => 'registration',
                    'Question sur la connexion' => 'login',
                    'Autre question' => 'other',
                ],
                'placeholder' => 'Choisissez un sujet',
            ])
            ->add('message', TextareaType::class, ['label' => 'Message']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactMessage::class,
        ]);
    }
}
