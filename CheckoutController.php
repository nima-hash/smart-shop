<?php
namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Service\CartService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/checkout')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private OrderService $orderService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger 
    ) {}

    #[Route('/proceed', name: 'app_checkout_proceed', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY', message: 'You must be logged in to proceed to checkout.')]
    public function proceed(Request $request): Response
    {
       
        $user = $this->getUser(); 

     
        $cart = $this->cartService->getCurrentCart();

        if ($cart->getCartItems()->isEmpty()) {
            $this->addFlash('error', 'Your cart is empty. Please add items before proceeding to checkout.');
            return $this->redirectToRoute('app_cart_index');
        }

        try {
           
            $order = $this->orderService->convertCartToOrder($cart, $user);

            $this->entityManager->flush(); 

            $this->cartService->clearCart();

            $this->addFlash('success', 'Your order has been placed successfully!');

            return $this->redirectToRoute('app_checkout_success', ['orderId' => $order->getId()]);

        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_cart_index');
        } catch (\Exception $e) {
            $this->logger->error('Error during checkout: ' . $e->getMessage(), ['exception' => $e, 'user' => $user->getId()]);
            $this->addFlash('error', 'An unexpected error occurred during checkout. Please try again.');
            return $this->redirectToRoute('app_cart_index');
        }
    }

    #[Route('/success/{orderId}', name: 'app_checkout_success', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function success(int $orderId): Response
    {
        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order || $order->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException('Order not found or you do not have permission to view it.');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }
}
