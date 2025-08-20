<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository; 
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\OrderRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/product')]
class ReviewController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ReviewRepository $reviewRepository;
    private OrderRepository $orderRepository;

    public function __construct(EntityManagerInterface $entityManager, ReviewRepository $reviewRepository, OrderRepository $orderRepository)
    {
        $this->entityManager = $entityManager;
        $this->reviewRepository = $reviewRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Handles the submission of a new review for a product.
     */
    #[Route('/{slug}/review/new', name: 'app_review_new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')] 
    public function new(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Product $product, OrderRepository $orderRepository, ReviewRepository $reviewRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to submit a review.');
        }

        // Check if the user has an existing review for this product
        $existingReview = $this->reviewRepository->findOneBy(['user' => $user, 'product' => $product]);

        // If an existing review is found, use it for the form, otherwise create a new one
        $review = $existingReview ?: new Review();

        if (!$existingReview) {

            // Check if the user is eligible to review this product
            $eligibleOrderItems = $this->orderRepository->findEligibleOrderItemsForReview($user, $this->reviewRepository);
            $isEligible = false;
            foreach ($eligibleOrderItems as $orderItem) {
                if ($orderItem->getProduct()->getId() === $product->getId()) {
                    $isEligible = true;
                    break;
                }
            }

            if (!$isEligible) {
                $this->addFlash('error', 'You are not eligible to review this product. You must have purchased it and the order must be delivered and paid.');
                return $this->redirectToRoute('app_customer_product_show', ['slug' => $product->getSlug()]);
            }
        }


        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setProduct($product);
            $review->setUser($user);

            $this->entityManager->persist($review); 
            $this->entityManager->flush();

            // Update the product's average rating
            $averageRating = $this->reviewRepository->getAverageRatingForProduct($product);
            $product->setRating($averageRating);
            $this->entityManager->flush();

            $this->addFlash('success', 'Your review has been submitted/updated successfully!');

            return $this->redirectToRoute('app_customer_profile_reviews');
        }
                                 

        return $this->render('customer/review/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'existingReview' => $existingReview,
        ]);
    }

    #[Route('/{slug}/review/{id}/delete', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Product $product, Review $review): Response
    {
        $user = $this->getUser();

        if (!$user || $review->getUser() !== $user || $review->getProduct() !== $product) {
            throw $this->createAccessDeniedException('You are not authorized to delete this review.');
        }

        if ($this->isCsrfTokenValid('delete' . $review->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($review);
            $this->entityManager->flush();

            $averageRating = $this->reviewRepository->getAverageRatingForProduct($product);
            $product->setRating($averageRating);
            $this->entityManager->flush();

            $this->addFlash('success', 'Your review has been deleted successfully.');
        } else {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
        }

        return $this->redirectToRoute('app_customer_profile_reviews');
    }


    /**
     * Displays all reviews for a specific product.
     */
    public function listForProduct(Product $product): Response
    {
        $reviews = $this->reviewRepository->findByProduct($product);
        $averageRating = $this->reviewRepository->getAverageRatingForProduct($product);

        return $this->render('review/_list_for_product.html.twig', [
            'product' => $product,
            'reviews' => $reviews,
            'averageRating' => $averageRating,
        ]);
    }



}
