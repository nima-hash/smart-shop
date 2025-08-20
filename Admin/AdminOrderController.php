<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/orders')]
#[IsGranted('ROLE_ADMIN')] 
class AdminOrderController extends AbstractController
{
    private OrderRepository $orderRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(OrderRepository $orderRepository, EntityManagerInterface $entityManager)
    {
        $this->orderRepository = $orderRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Display a list of all orders.
     */
    #[Route('/', name: 'app_admin_order_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        if ($status) {

        $orders = $this->orderRepository->findBy(['status' => $status], ['createdAt' => 'DESC']);
    
    } else {

        $orders = $this->orderRepository->findBy([], ['createdAt' => 'DESC']);
    }

    return $this->render('admin/orders/index.html.twig', [
            'orders' => $orders,
            'active_section' => 'Orders',
            'order_status' => $status,
        ]);
    }

    /**
     * Display details for a single order and provide management forms.
     */
    #[Route('/{id}', name: 'app_admin_order_show', methods: ['GET', 'POST'])]
    public function show(Request $request, Order $order): Response
    {
        // Define the available order statuses for the status update form
        $availableStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        // Handle status update form submission
        if ($request->isMethod('POST') && $request->request->has('status_form')) {
            $submittedToken = $request->request->get('_token');
            if ($this->isCsrfTokenValid('update_order_status_' . $order->getId(), $submittedToken)) {
                $newStatus = $request->request->get('status');
                if (in_array($newStatus, $availableStatuses)) {
                    $order->setStatus($newStatus);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'Order status updated successfully!');
                } else {
                    $this->addFlash('error', 'Invalid status provided.');
                }
            }
            return $this->redirectToRoute('app_admin_order_show', [
                'id' => $order->getId(),
                'active_section' => 'Orders'
            ]);
        }

        // Handle paid status toggle
        if ($request->isMethod('POST') && $request->request->has('paid_status_form')) {
            $submittedToken = $request->request->get('_token');
            if ($this->isCsrfTokenValid('toggle_paid_status_' . $order->getId(), $submittedToken)) {
                $order->setPaid(!$order->isPaid());
                $this->entityManager->flush();
                $this->addFlash('success', 'Order payment status updated successfully!');
            }
            return $this->redirectToRoute('app_admin_order_show', [
                'id' => $order->getId(),
                'active_section' => 'Orders'
            ]);
        }

        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
            'available_statuses' => $availableStatuses,
            'active_section' => 'Orders'
        ]);
    }
}
