<?php

namespace App\Controller;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Form\CreditCardDetailsType;
use App\Form\PaymentMethodType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormBuilder;

#[Route('/customer/payment_method')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PaymentController extends AbstractController
{
    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->security = $security;
        $this->entityManager = $entityManager;
    }

    /**
     * Display a list of all payment methods for the current user.
     */
    #[Route('/', name: 'app_customer_payment_method_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->security->getUser();
        if (!$user) {
             throw $this->createAccessDeniedException('You must be logged in to view your payment methods.');
        }

        $paymentMethods = $user->getPaymentMethod();

        return $this->render('customer/profile/index.html.twig', [
            'payment_methods' => $paymentMethods,
            'active_section' => 'payment',
            'sidebar' => 'profile',
        ]);
    }

    /**
     * Handle the form to add a new payment method.
     */
    #[Route('/new', name: 'app_customer_payment_method_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, PaymentMethod $paymentMethod): Response
    {
        if (!$request->isMethod('POST')) {
            $paymentMethod->setType('credit_card');

        }

        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set the user relationship before persisting
            $user = $this->security->getUser();
            if (!$user) {
                throw $this->createAccessDeniedException('You must be logged in to add a payment method.');
            }
            $paymentMethod->setUser($user);

            // Get the submitted payment type
            $submittedType = $form->get('type')->getData();

            // If the payment type is credit card, derive lastFourDigits
            if ($submittedType === 'credit_card') {
                $creditCardDetails = $form->get('creditCardDetails')->getData();
                $cardNumber = $creditCardDetails['cardNumber'] ?? null;
                if ($cardNumber) {
                    $paymentMethod->setLastFourDigits(substr($cardNumber, -4));
                }
            } else {
                $paymentMethod->setLastFourDigits(null);
            }

            $this->entityManager->persist($paymentMethod);
            $this->entityManager->flush();

            $this->addFlash('success', 'Payment method added successfully!');

            return $this->redirectToRoute('app_customer_payment_method_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/profile/index.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form->createView(),
            'active_section' => 'add_payment',
            'sidebar' => 'profile',
        ]);
    }

    #[Route('/payment_method/{id}/edit', name: 'app_customer_payment_method_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PaymentMethod $paymentMethod): Response
    {
        // Security check:
        if ($paymentMethod->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not authorized to edit this payment method.');
        }

        $form = $this->createForm(PaymentMethodType::class, $paymentMethod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the submitted payment type
            $submittedType = $form->get('type')->getData();

            // If the payment type is credit card, derive lastFourDigits
            if ($submittedType === 'credit_card') {
                // If credit card details were submitted 
                if ($form->has('creditCardDetails')) {
                    $creditCardDetails = $form->get('creditCardDetails')->getData();
                    $cardNumber = $creditCardDetails['cardNumber'] ?? null;
                    if ($cardNumber) {
                        $paymentMethod->setLastFourDigits(substr($cardNumber, -4));
                    }
                }
            }
            // For PayPal, update based on submitted email
            elseif ($submittedType === 'paypal') {
                 if ($form->has('paypalDetails')) {
                    $paypalDetails = $form->get('paypalDetails')->getData();
                    $paypalEmail = $paypalDetails['paypalEmail'] ?? null;
                    if ($paypalEmail) {
                        $paymentMethod->setLastFourDigits(substr($paypalEmail, 0, 4) . '...');
                    }
                }
            }


            $this->entityManager->flush();

            $this->addFlash('success', 'Payment method updated successfully!');

            return $this->redirectToRoute('app_customer_payment_method_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/profile/index.html.twig', [
            'payment_method' => $paymentMethod,
            'form' => $form->createView(),
            'active_section' => 'edit_payment',
            'sidebar' => 'profile',
        ]);
    }

    /**
     * Delete a payment method.
     */
    #[Route('/{id}', name: 'app_customer_payment_method_delete', methods: ['POST'])]
    public function delete(Request $request, PaymentMethod $paymentMethod): Response
    {
        // Security check: ensure the current user owns this payment method
        if ($paymentMethod->getUser() !== $this->security->getUser()) {
            throw $this->createAccessDeniedException('You are not authorized to delete this payment method.');
        }

        if ($this->isCsrfTokenValid('delete' . $paymentMethod->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($paymentMethod);
            $this->entityManager->flush();
            $this->addFlash('success', 'Payment method deleted.');
        } else {
             $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_customer_payment_method_index', [], Response::HTTP_SEE_OTHER);
    }
}
