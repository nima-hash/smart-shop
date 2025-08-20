<?php

namespace App\Controller;

use App\Entity\RegistrationConfirmation;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;



class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier, readonly UserRepository $userRepository)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $plainPassword = $form->get('plainPassword')->getData();

                // encode the plain password
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));


                $confirmation = new RegistrationConfirmation();
                $confirmation->setUser($user);
                // Set the validation time for 1 hour from now
                $confirmation->setExpiresAt(new DateTimeImmutable('+1 hour'));
                $confirmation->setToken(bin2hex(random_bytes(20)));
                $user->setRegistrationConfirmation($confirmation);

                $entityManager->persist($user);
                $entityManager->flush();

                // generate a signed url and email it to the user
                $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    (new TemplatedEmail())
                        ->from(new Address('support@bithorizon.de', 'Your Shop'))
                        ->to((string) $user->getEmail())
                        ->subject('Please Confirm your Email')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                );

                $email = $form->get('email')->getData();
                return $this->redirectToRoute('app_confirmation_sent', [
                    'expirationTime' => $confirmation->getExpiresAt()->getTimestamp(),
                    'email' => $email,
                ]);
            } else {
                $emailErrors = $form->get('email')->getErrors(true);

                foreach ($emailErrors as $error) {
                    if (str_contains($error->getMessage(), 'There is already an account with this email')) {
                        
                        $existingUser = $this->userRepository->findOneBy(['email' => $form->get('email')->getData()]);

                        if ($existingUser && !$existingUser->isVerified()) {

                            $confirmation = $existingUser->getRegistrationConfirmation();
                            $now = new DateTimeImmutable('now');

                            if ($confirmation && $confirmation->getExpiresAt() > $now) {
                                return $this->redirectToRoute('app_confirmation_sent', [
                                    'expirationTime' => $confirmation->getExpiresAt()->getTimestamp(),
                                    'email' => $form->get('email')->getData()
                                ]);
                            } else {
                                return $this->redirectToRoute('app_resend_confirmation_email', [
                                    'email' => $form->get('email')->getData(),
                                ]);
                            }
                        }
                    }
                }
            }
        }


        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }
        $$user->setRoles(["Role_USER"]);
        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_register');
    }
}
