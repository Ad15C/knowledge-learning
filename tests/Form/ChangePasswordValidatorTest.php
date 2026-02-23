<?php

namespace App\Tests\Form;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChangePasswordValidatorTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testPasswordConstraints()
    {
        $user = new User();

        $tests = [
            ['password' => 'short1!', 'error' => 'au moins 8 caractères'],
            ['password' => 'alllowercase123!', 'error' => 'une majuscule'],
            ['password' => 'ALLUPPERCASE123!', 'error' => 'une minuscule'],
            ['password' => 'NoNumbers!', 'error' => 'un chiffre'],
            ['password' => 'NoSpecial123', 'error' => 'caractère spécial'],
        ];

        foreach ($tests as $t) {
            $user->setPlainPassword($t['password']);
            $errors = $this->validator->validateProperty($user, 'plainPassword');
            $this->assertNotEmpty($errors, 'Mot de passe invalide: ' . $t['password']);
            $this->assertStringContainsString($t['error'], (string)$errors[0]);
        }

        // Mot de passe valide
        $user->setPlainPassword('ValidPass123!');
        $errors = $this->validator->validateProperty($user, 'plainPassword');
        $this->assertCount(0, $errors);
    }
}