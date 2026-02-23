<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Nouveau mot de passe'],
                'second_options' => ['label' => 'Confirmez le mot de passe'],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez saisir un mot de passe.']),
                    new Assert\Length(['min' => 8, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.']),
                    new Assert\Regex(['pattern' => '/[A-Z]/', 'message' => 'Le mot de passe doit contenir au moins une majuscule.']),
                    new Assert\Regex(['pattern' => '/[a-z]/', 'message' => 'Le mot de passe doit contenir au moins une minuscule.']),
                    new Assert\Regex(['pattern' => '/[0-9]/', 'message' => 'Le mot de passe doit contenir au moins un chiffre.']),
                    new Assert\Regex(['pattern' => '/[\W_]/', 'message' => 'Le mot de passe doit contenir au moins un caractère spécial.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id'   => 'change_password',
        ]);
    }
}
