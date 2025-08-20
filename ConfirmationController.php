<?php

namespace App\Controller;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;



class ConfirmationController extends AbstractController
{
    
    public function __construct(
        private VerifyEmailHelperInterface $emailVerifier,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {
    }
    /**
     * Renders a page informing the user that a confirmation email has been sent.
     */
    #[Route('/confirmation/sent', name: 'app_confirmation_sent')]
    public function confirmationSent(Request $request): Response
    {
        $expirationTime = $request->query->get('expirationTime', 0);
        $email = $request->query->get('email', "");

        return $this->render('registration/confirmation/sent.html.twig', [
            'expirationTime' => $expirationTime,
            'email' => $email,
        ]);
    }

    #[Route('/resend-confirmation-email', name: 'app_resend_confirmation_email')]
    public function resendConfirmationEmail(UserRepository $userRepository, Request $request): Response
    {

        $email = $request->query->get('email');

        $user = $userRepository->findOneBy(['email' => $email]);

        // Check if the user is logged in
        if (!$user) {
            $this->addFlash('error', 'Your Email could not be found.');
            return $this->redirectToRoute('app_login');
        }

        // If the user is already verified, no need to resend
        if ($user->isVerified()) {
            $this->addFlash('success', 'Your account is already verified.');
            return $this->redirectToRoute('app_home');
        }

        // Create a signed URL for email confirmation
        $signatureComponents = $this->emailVerifier->generateSignature(
            'app_verify_email',
            (string)$user->getId(),
            (string)$user->getEmail(),
            ['id' => $user->getId()]
        );

        // Re-generate and send the confirmation email
        $emailMessage = (new TemplatedEmail())
            ->from(new Address('support@bithorizon.de', 'Your Shop'))
            ->to((string) $user->getEmail())
            ->subject('Please Confirm your Email (Resent)')
            ->htmlTemplate('registration/confirmation_email.html.twig')
            ->context(['signedUrl' => $signatureComponents->getSignedUrl()]);
        
        $this->mailer->send($emailMessage);


        $this->addFlash('info', 'A new confirmation email has been sent. Please check your inbox.');
        $this->addFlash('info', 'This could take up to 5 minutes, as this is not production.');
        return $this->redirectToRoute('app_confirmation_sent', [
            'email' => $email,
            'expirationTime' => $signatureComponents->getExpiresAt()->getTimestamp(),
        ]);
    }
}