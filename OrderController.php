<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;



#[Route('/customer/orders')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class OrderController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager) 
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_customer_orders', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myOrders(OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();


        $orders = $orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('customer/profile/index.html.twig', [
            'orders' => $orders,
            'sidebar' => 'profile',
            'active_section' => 'past_orders'
        ]);
    }
    /**
     * Displays the details of a specific order.
     */
    #[Route('/{id}', name: 'app_customer_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {

        $user = $this->getUser();
        if (!$user || $order->getUser() !== $user) {
            throw $this->createAccessDeniedException('You are not authorized to view this order.');
        }

        return $this->render('customer/profile/index.html.twig', [
            'order' => $order,
            'active_section' => 'show_order',
            'sidebar' => 'profile'
        ]);
    }

     /**
     * Handles cancelling an order.
     */
    #[Route('/{id}/cancel', name: 'app_customer_order_cancel', methods: ['POST'])]
    public function cancel(Request $request, Order $order): Response
    {
        // Security check
        $user = $this->getUser();
        if (!$user || $order->getUser() !== $user) {
            throw $this->createAccessDeniedException('You are not authorized to cancel this order.');
        }

        // Validate CSRF token
        if ($this->isCsrfTokenValid('cancel' . $order->getId(), $request->request->get('_token'))) {
            $cancellableStatuses = ['pending', 'processing']; 
            if (in_array($order->getStatus(), $cancellableStatuses)) {
                $order->setStatus('cancelled');
                $this->entityManager->flush();
                $this->addFlash('success', 'Order #' . $order->getId() . ' has been successfully cancelled.');
            } else {
                $this->addFlash('error', 'Order #' . $order->getId() . ' cannot be cancelled at its current status.');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
        }

        return $this->redirectToRoute('app_customer_order_show', ['id' => $order->getId()]);
    }
}
