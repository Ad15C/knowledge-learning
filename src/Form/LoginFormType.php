<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('_username', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'Votre email'],
            ])
            ->add('_password', PasswordType::class, [
                'label' => 'Mot de passe',
                'attr' => ['placeholder' => 'Votre mot de passe'],
            ])
            ->add('_remember_me', CheckboxType::class, [
                'label' => 'Se souvenir de moi',
                'required' => false,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // pas de data_class, car form_login ne mappe pas sur User
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'authenticate', // identifiant unique pour le login
        ]);
    }
}