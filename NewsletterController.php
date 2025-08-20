<?php

namespace App\Controller;

use App\Entity\Subscriber;
use App\Form\NewsletterSubscriptionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

class NewsletterController extends AbstractController
{
    /**
     * Renders the subscription form and handles its submission for double opt-in.
     */
    #[Route('/newsletter/subscribe', name: 'app_newsletter_subscribe')]
    public function subscribe(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        $subscriber = new Subscriber();
        $form = $this->createForm(NewsletterSubscriptionType::class, $subscriber);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingSubscriber = $entityManager->getRepository(Subscriber::class)->findOneBy(['email' => $subscriber->getEmail()]);
            
            if ($existingSubscriber) {
                if (!$existingSubscriber->isIsSubscribed()) {
                    $this->sendConfirmationEmail($mailer, $existingSubscriber);
                    $this->addFlash('success', 'You have already subscribed, but your email is not yet confirmed. We have resent the confirmation link. Please check your inbox.');
                    $this->addFlash('info', 'This could take up to 5 minutes, as this is not production.');

                } else {
                    $this->addFlash('success', 'You are already subscribed to our newsletter!');
                }
            } else {
                // Generate a unique token for the confirmation link
                $subscriber->setToken(Uuid::v4());
                // Set isSubscribed to false initially
                $subscriber->setIsSubscribed(false);

                $entityManager->persist($subscriber);
                $entityManager->flush();

                // Send the confirmation email
                $this->sendConfirmationEmail($mailer, $subscriber);

                $this->addFlash('success', 'Thanks for subscribing! Please check your email to confirm your subscription.');
                $this->addFlash('info', 'This could take up to 5 minutes, as this is not production.');

            }

            return $this->redirectToRoute('app_home');
        }

        return $this->render('newsletter/_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Handles the confirmation link from the email.
     */
    #[Route('/newsletter/confirm/{token}', name: 'app_newsletter_confirm')]
    public function confirm(string $token, EntityManagerInterface $entityManager): Response
    {
        $subscriber = $entityManager->getRepository(Subscriber::class)->findOneBy([
            'token' => $token,
            'is_subscribed' => false
        ]);

        if ($subscriber) {
            $subscriber->setIsSubscribed(true);
            $entityManager->flush();

            $this->addFlash('success', 'Thank you! Your subscription has been confirmed.');
        } else {
            $this->addFlash('danger', 'Invalid or expired confirmation link.');
        }

        return $this->redirectToRoute('newsletter_confirmation_landing');
    }

    #[Route('/newsletter/confirmation/success', name: 'newsletter_confirmation_landing')]
    public function confirmationLanding(): Response
    {
        return $this->render('newsletter/confirmation_success.html.twig');
    }

    /**
     * Unsubscribes a user using a unique token.
     */
    #[Route('/newsletter/unsubscribe/{token}', name: 'app_newsletter_unsubscribe')]
    public function unsubscribe(string $token, EntityManagerInterface $entityManager): Response
    {
        $subscriber = $entityManager->getRepository(Subscriber::class)->findOneBy(['token' => $token]);

        if ($subscriber) {
            $subscriber->setIsSubscribed(false);
            $entityManager->flush();

            $this->addFlash('success', 'You have been successfully unsubscribed.');
        } else {
            $this->addFlash('danger', 'Invalid unsubscribe link.');
        }

        return $this->redirectToRoute('newsletter_confirmation_landing');
    }

    /**
     * Sends a confirmation email to the subscriber.
     */
    private function sendConfirmationEmail(MailerInterface $mailer, Subscriber $subscriber): void
    {
        $email = (new Email())
            ->from('noreply@ybithorizon.de')
            ->to($subscriber->getEmail())
            ->subject('Please Confirm Your Subscription')
            ->html($this->renderView('newsletter/confirmation_email.html.twig', [
                'token' => $subscriber->getToken()
            ]));

        $mailer->send($email);
    }
}
