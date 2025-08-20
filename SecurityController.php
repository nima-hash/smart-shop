<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;



class SecurityController extends AbstractController
{

    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Security $security): Response
    {

        $user = $security->getUser();
        if ($user && $user->isVerified()) {
            return $this->redirectToRoute('app_home');
        }

        // Check if the user is logged in but not verified.
        if ($user && !$user->isVerified()) {
            $this->addFlash('warning', 'Please verify your email address to log in.');
            return $this->redirectToRoute('app_resend_confirmation_email');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): RedirectResponse
    {
        $redirectUri = $this->generateUrl('connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $clientRegistry
            ->getClient('google') 
            ->redirect(['openid', 'profile', 'email'], ['redirect_uri' => $redirectUri]);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck()
    {

        return $this->redirectToRoute('app_home');
    }


    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }


}
