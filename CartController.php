<?php
namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Product; 
use App\Service\CartService; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;


#[Route('/cart')] 
class CartController extends AbstractController
{

    public function __construct(
        private CartService $cartService,
        private LoggerInterface $logger
    ) {}

    /**
     * Displays the current shopping cart.
     */
    #[Route('/', name: 'app_cart_index')]
    public function index(): Response
    {
        // Get the current cart from your CartService
        $cart = $this->cartService->getCurrentCart();
        // Render the cart template, passing the cart object to it
        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    /**
     * Adds a product to the cart.
     */
    #[Route('/add/{slug}', name: 'app_cart_add', methods: ['POST'])]
    public function add( #[MapEntity(mapping: ['slug' => 'slug'])] Product $product, Request $request, CartService $cartService): Response 
    {

        $quantity = $request->request->getInt('quantity', 1);

        if ($quantity <= 0) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Quantity must be at least 1.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            // Fallback for non-AJAX if needed
            $this->addFlash('error', 'Quantity must be at least 1.');
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }

        try {
            $cart = $this->cartService->addProduct($product, $quantity);
            
            if ($request->isXmlHttpRequest()) {

                return new JsonResponse([
                    'success' => true,
                    'message' => sprintf('"%s" added to cart!', $product->getName()),
                    'cartItemCount' => $cartService->getTotalQuantity() 
                ], JsonResponse::HTTP_OK);
            }

            // Fallback for non-AJAX
            $this->addFlash('success', sprintf('"%s" added to cart!', $product->getName()));
            return $this->redirectToRoute('app_cart_index');

        } catch (\Exception $e) {
            $this->logger->error('Error adding to cart: ' . $e->getMessage(), ['exception' => $e, 'product_slug' => $product->getSlug(), 'quantity' => $quantity]);

            // For AJAX, return JSON error with appropriate HTTP status
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Failed to add item to cart. Please try again. (' . $e->getMessage() . ')'
                ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR); 
            }
            // Fallback for non-AJAX
            $this->addFlash('error', 'Could not add item to cart: ' . $e->getMessage());
            return $this->redirectToRoute('app_product_show', ['slug' => $product->getSlug()]);
        }
    }


    /**
     * Removes a product from the cart.
     */
    #[Route('/remove/{slug}', name: 'app_cart_remove', methods: ['POST'])]
    public function remove( #[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response 
    {
        // Use the CartService to remove the product
        $this->cartService->removeProduct($product);

        $this->addFlash('success', sprintf('"%s" removed from cart.', $product->getName()));

        return $this->redirectToRoute('app_cart_index');
    }

    /**
     * Updates the quantity of a product in the cart.
     */
    #[Route('/update/{slug}', name: 'app_cart_update', methods: ['POST'])]
    public function update( #[MapEntity(mapping: ['slug' => 'slug'])] Product $product, Request $request ): Response 
    {
        // Get the new quantity from the POST request (default to 0 if not provided or invalid)
        $quantity = $request->request->getInt('quantity', 0);

        if ($quantity < 0) { 
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Quantity cannot be negative.'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'Quantity cannot be negative.');
            return $this->redirectToRoute('app_cart_index');
        }

        try {
            $cart = $this->cartService->updateProductQuantity($product, $quantity);

            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Cart updated successfully!',
                    'cartItemCount' => $cart->getCartItems()->count(),
                ], JsonResponse::HTTP_OK);
            }

            $this->addFlash('success', 'Cart updated successfully.');
            return $this->redirectToRoute('app_cart_index');

            } catch (\Exception $e) {
                $this->logger->error('Error updating cart: ' . $e->getMessage(), ['exception' => $e]);
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Failed to update cart: ' . $e->getMessage()
                    ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
                }
                $this->addFlash('error', 'Failed to update cart: ' . $e->getMessage());
                return $this->redirectToRoute('app_cart_index');
        }
    }

    /**
     * Clears all items from the current cart.
     */
    #[Route('/clear', name: 'app_cart_clear', methods: ['POST'])]
    public function clear(): Response
    {
        // Use the CartService to clear the entire cart
        $this->cartService->clearCart();

        $this->addFlash('success', 'Your cart has been cleared.');

        return $this->redirectToRoute('app_cart_index');
    }


}