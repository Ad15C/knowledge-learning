<?php

namespace App\Form\Admin;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ]);

        if ($options['allow_roles_edit']) {
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'expanded' => true,   // checkbox
                'multiple' => true,   // roles = array en base
                'required' => false,
                'help' => 'ROLE_USER est automatique. Coche "Administrateur" pour donner ROLE_ADMIN.',
            ]);

            // Nettoyage AVANT hydratation dans l'entité
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();
                if (!is_array($data)) {
                    return;
                }

                $roles = (array) ($data['roles'] ?? []);

                // On garde uniquement ROLE_ADMIN si coché, sinon []
                $data['roles'] = in_array('ROLE_ADMIN', $roles, true) ? ['ROLE_ADMIN'] : [];

                $event->setData($data);
            });
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'allow_roles_edit' => true,
        ]);

        $resolver->setAllowedTypes('allow_roles_edit', 'bool');
    }
}