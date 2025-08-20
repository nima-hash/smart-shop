<?php
namespace App\Controller\Admin;

use App\Entity\Cart;
use Doctrine\ORM\EntityManager;
use App\Entity\CartItem;
use App\Form\AdminCartItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin/cart')]
#[IsGranted('ROLE_ADMIN')] // Restrict access to this entire controller to admin users only
class AdminCartController extends AbstractController
{
    #[Route('/{id}', name: 'app_admin_cart')]
    public function index(Cart $cart) {

        return $this->render('admin/cart/index.html.twig', [
            'cart' => $cart,
            'active_section' => 'Users',
        ]);

    }

    #[Route('/item/{id}/edit', name: 'app_admin_cart_item_edit')]
    public function editItem(CartItem $cartItem, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AdminCartItemType::class, $cartItem);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Cart item quantity updated successfully.');
            
            // Redirect back to the cart details page
            return $this->redirectToRoute('app_admin_cart_show', ['id' => $cartItem->getCart()->getId()]);
        }

        return $this->render('admin/cart/edit_item.html.twig', [
            'cartItem' => $cartItem,
            'form' => $form->createView(),
            'active_section' => 'Users',
        ]);
    }

    /**
     * Deletes a specific cart.
     */
    #[Route('/{id}/delete', name: 'app_admin_cart_delete', methods: ['POST'])]
    public function deleteCart(Cart $cart, EntityManagerInterface $entityManager): Response
    {
        $userId = $cart->getUser()->getId();
        $entityManager->remove($cart);
        $entityManager->flush();
        $this->addFlash('success', 'Cart ' . $cart->getId() . ' has been deleted.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $userId]);
    }

    /**
     * Deletes a specific cart item.
     */
    #[Route('/item/{id}/delete', name: 'app_admin_cart_item_delete', methods: ['POST'])]
    public function deleteItem(CartItem $cartItem, EntityManagerInterface $entityManager): Response
    {
        $cartId = $cartItem->getCart()->getId();
        $entityManager->remove($cartItem);
        $entityManager->flush();
        $this->addFlash('success', 'Cart item has been removed.');

        return $this->redirectToRoute('app_admin_cart_show', ['id' => $cartId]);
    }
}

?>