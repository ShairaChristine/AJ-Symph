<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login", methods={"GET","POST"})
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
        // 'security/login.html.twig'
        return $this->render('security/signup.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /**
     * @Route("/signup", name="app_signup", methods={"GET","POST"})
     */
    public function signup(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $userPasswordHasher,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response
    {
        $error = null;

        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$csrfTokenManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('signup', $csrfToken))) {
                $error = 'Invalid CSRF token.';
            } else {
                $email = trim((string) $request->request->get('email', ''));
                $fullName = trim((string) $request->request->get('name', ''));
                $plainPassword = (string) $request->request->get('password', '');
                $confirmPassword = (string) $request->request->get('confirm_password', '');

                if ($email === '' || $plainPassword === '' || $confirmPassword === '') {
                    $error = 'Please fill out all required fields.';
                } elseif ($plainPassword !== $confirmPassword) {
                    $error = 'Passwords do not match.';
                } else {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                    if ($existingUser !== null) {
                        $error = 'An account with this email already exists.';
                    } else {
                        $user = new User();
                        $user
                            ->setEmail($email)
                            ->setFullName($fullName !== '' ? $fullName : null)
                            ->setRoles(['ROLE_USER']);

                        $hashedPassword = $userPasswordHasher->hashPassword($user, $plainPassword);
                        $user->setPassword($hashedPassword);

                        $entityManager->persist($user);
                        $entityManager->flush();

                        return $this->redirectToRoute('app_login');
                    }
                }
            }
        }

        return $this->render('security/signup.html.twig', [
            'error' => $error,
        ]);
    }
}
