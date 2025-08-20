<?php

namespace App\Controller\Admin;

use App\Entity\Subscriber;
use App\Repository\SubscriberRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/subscription')]
#[IsGranted('ROLE_ADMIN')]
class AdminSubscriptionController extends AbstractController
{
    /**
     * Shows a list of all email subscriptions, with filtering options.
     */
    #[Route('/', name: 'app_admin_subscription_index')]
    public function index(
        SubscriberRepository $subscriberRepository,
        UserRepository $userRepository,
        Request $request
    ): Response {
        // Get the filter from the request query parameters, default to 'all'
        $filter = $request->query->get('filter', 'all');

        // Fetch subscriptions based on the filter
        switch ($filter) {
            case 'subscribed':
                $subscriptions = $subscriberRepository->findBy(['is_subscribed' => true], ['createdAt' => 'DESC']);
                break;
            case 'unsubscribed':
                $subscriptions = $subscriberRepository->findBy(['is_subscribed' => false], ['unsubscribedAt' => 'DESC']);
                break;
            default:
                $subscriptions = $subscriberRepository->findAll();
                break;
        }

        $userEmails = [];
        foreach ($userRepository->findAll() as $user) {
            $userEmails[$user->getEmail()] = $user;
        }

        return $this->render('admin/subscription/index.html.twig', [
            'subscriptions' => $subscriptions,
            'userEmails' => $userEmails,
            'currentFilter' => $filter,
            'active_section' => 'Subscriptions',
        ]);
    }

    /**
     * Unsubscribes an email from the list.
     */
    #[Route('/{id}/unsubscribe', name: 'app_admin_subscription_unsubscribe')]
    public function unsubscribe(Subscriber $subscription, EntityManagerInterface $entityManager): Response
    {
        if ($subscription->isIsSubscribed()) {
            $subscription->setIsSubscribed(false);
            $subscription->setUnsubscribedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Email ' . $subscription->getEmail() . ' has been unsubscribed.');
        }

        return $this->redirectToRoute('app_admin_subscription_index', [
            'active_section' => 'Subscription'
        ], Response::HTTP_SEE_OTHER);
    }
}
