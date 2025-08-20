<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\CustomerProfileType;
use App\Form\CustomerPasswordChangeType;
use App\Repository\OrderRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Controller for the customer profile pages.
 */
#[Route('/customer/profile')]
// Ensures only authenticated users can access these routes
#[IsGranted('ROLE_USER')]
class CustomerProfileController extends AbstractController
{

    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }
    /**
     * Main profile page
     */
    #[Route('/', name: 'app_customer_profile_index')]
    public function index(Request $request): Response
    {
        // Get the current authenticated user
        $user = $this->getUser();

        $personalInfo = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'address' => '123 Main Street, Anytown, USA 12345',
            'profile_photo_url' => 'https://via.placeholder.com/150',
        ];

        $form = $this->createForm(CustomerProfileType::class, $user);

        // This will render the main profile layout with the personal info template inside.
        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'personal_info',
            'personalInfo' => $personalInfo,
            'form' => $form->createView(),
            'sidebar' => 'profile',
        ]);
    }


    /**
     * Displays and handles the security section (password reset).
     */
    #[Route('/security', name: 'app_customer_profile_security')]
    public function security(Request $request,
                            EntityManagerInterface $entityManager,
                            UserPasswordHasherInterface $passwordHasher,
                            Security $security,
                            ): Response
    {
        $user = $security->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }


        // Create the form using the new CustomerProfileType
        $form = $this->createForm(CustomerPasswordChangeType::class, $user);

        // Handle the form submission
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle the profile photo upload
            $currentPassword = $form->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {

                $form->get('currentPassword')->addError(new FormError('Invalid current password.'));
            } else {
                $newPassword = $form->get('newPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);

                $entityManager->flush();

                $this->addFlash('success', 'Password updated successfully!');
                return $this->redirectToRoute('app_customer_profile_index');
            }
        }
        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'security',
            'form' => $form->createView(),
            'sidebar' => 'profile',
        ]);
    }

    /**
     * Displays and handles the payment information section.
     */
    #[Route('/payment', name: 'app_customer_profile_payment')]
    public function payment(Request $request): Response
    {
        $paymentMethods = [
            ['card_type' => 'Visa', 'last_four' => '1234', 'expires' => '12/25'],
            ['card_type' => 'Mastercard', 'last_four' => '5678', 'expires' => '05/27'],
        ];

        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'payment',
            'paymentMethods' => $paymentMethods,
            'sidebar' => 'profile',
        ]);
    }

    /**
     * Displays the customer's past orders.
     */
    #[Route('/past-orders', name: 'app_customer_profile_past_orders')]
    public function pastOrders(OrderRepository $orderRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }


        $pastOrders = $orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'past_orders',
            'orders' => $pastOrders,
            'sidebar' => 'profile',
        ]);
    }


    /**
     * Displays the customer's past reviews.
     */
    #[Route('/reviews', name: 'app_customer_profile_reviews')]
    public function reviews(ReviewRepository $reviewRepository, OrderRepository $orderRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to view reviews.');
        }


        $reviews = $reviewRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        $eligibleOrderItemsForReview = $orderRepository->findEligibleOrderItemsForReview($user, $reviewRepository);

        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'reviews',
            'reviews' => $reviews,
            'eligibleProductsForReview' => $eligibleOrderItemsForReview,
            'sidebar' => 'profile',
        ]);
    }

    #[Route('/personal-info', name: 'app_customer_profile_personal_info')]
    public function personalInfo(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        User $user
    ): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }
                // --- handling defaultShippingAddress ---
        $defaultAddress = $user->getDefaultShippingAddress(); 
        if (!$defaultAddress) { 
            $defaultAddress = new Address();
            $defaultAddress->setUser($user); 
            $user->setDefaultShippingAddress($defaultAddress);
        }

        $form = $this->createForm(CustomerProfileType::class, $user);
        // Handle the form submission
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle the profile photo upload
            $profilePhotoFile = $form->get('profilePhoto')->getData();

            if ($profilePhotoFile) {
                $originalFilename = pathinfo($profilePhotoFile->getClientOriginalName(), PATHINFO_FILENAME);
                //  handle the filename
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$profilePhotoFile->guessExtension();

                try {
                    $profilePhotoFile->move(
                        $this->getParameter('profile_photos_directory'), 
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'There was an error uploading your profile picture.');
                    return $this->redirectToRoute('app_customer_profile_personal_info');
                }

                $user->setProfilePhoto($newFilename);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your personal information has been updated successfully!');
            return $this->redirectToRoute('app_customer_profile_personal_info');
        }

        return $this->render('customer/profile/index.html.twig', [
            'active_section' => 'personal_info',
            'form' => $form->createView(),
            'sidebar' => 'profile',
        ]);
    }
}