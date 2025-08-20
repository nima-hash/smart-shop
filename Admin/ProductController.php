<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\ProductType;
use App\Form\AdminReviewType;
use App\Repository\ProductRepository;
use App\Controller\Service\UploaderHelper;
use App\Entity\Review;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;

#[IsGranted('ROLE_ADMIN')]
final class ProductController extends AbstractController
{
    #[Route('/admin/view/product/{status}', name: 'app_product', defaults: ['status' => 'all'])]
    public function index(ProductRepository $productRepository, string $status): Response
    {
        $products = match ($status) {
            'published' => $productRepository->findBy(['isPublished' => true]),
            'draft'     => $productRepository->findBy(['isPublished' => false]),
            'all'       => $productRepository->findAll(),
            default     => $productRepository->findAll(), // Fallback for invalid status
        };

        return $this->render('admin/product/index.html.twig', [
            'products' => $products,
            'active_section' => 'Products',
            'currentStatus' => $status
        ]);
    }

    #[Route('/admin/add/product', name: 'app_product_add')]
    public function new(Request $request, EntityManagerInterface $entityManager, UploaderHelper $uploaderHelper) 
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())         
        {
            $product->setCreatedAt(new \DateTimeImmutable());

            $uploadedFiles = $form->get('image_files')->getData();
            $imagePaths = [];
            if(count($uploadedFiles) > 0)
            {

                $category = $form->get('category')->getData()->getName();
                $name = $form->get('name')->getData();
                $slug = new Slugify;
                $fileSavePath = 'product_images/' . $slug-> slugify($category) . '/' . $slug-> slugify($name);
                $uploadedThumbnail = $form->get('thumbnail_file')->getData();
                foreach ($uploadedFiles as $index => $uploadedFile) {
                    $isThumbnail = $index === 0;
                    $newFilename = $uploaderHelper->uploadProductImage($uploadedFile, $fileSavePath,  $isThumbnail);

                    if ($isThumbnail) {
                        if ($uploadedThumbnail) {
                            $newThumbnailFilename = $uploaderHelper->uploadProductImage($uploadedThumbnail, $fileSavePath,  $isThumbnail);
                            $product->setThumbnail($slug-> slugify($category) . '/' . $slug-> slugify($name) . '/thumb_' . $newThumbnailFilename);
                        } else {
                            $product->setThumbnail($slug-> slugify($category) . '/' . $slug-> slugify($name) . '/thumb_' . $newFilename);
                        }
                    }

                    $imagePaths[] = $slug-> slugify($category) . '/' . $slug-> slugify($name) . '/' . $newFilename;
                }
            }
            $product->setImage($imagePaths);

            
            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product ' . $product->getName() . ' is created successfully.');
            return $this->redirectToRoute('app_product', [
                'active_section' => 'Products',
            ]);
        }

        return $this->render('admin/product/new.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
            'active_section' => 'Products',

        ]);
    }

    #[Route('/admin/product/{slug}', name: 'app_product_show')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Product $product): Response
    {

        return $this->render('admin/product/show.html.twig', [
            'product' => $product,
            'active_section' => 'Products',
        ]);
    }

    #[Route('/admin/product/edit/{slug}', name: 'app_product_edit')]
    public function edit(EntityManagerInterface $entityManager, #[MapEntity(mapping:['slug' => 'slug'])] Product $product, Request $request, UploaderHelper $uploaderHelper): Response
    {   
        $form = $this->createForm(ProductType::class, $product);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {

            $uploadedFiles = $form->get('image_files')->getData();
            $imagePaths = [];
            if(count($uploadedFiles) > 0)
            {

                $category = $form->get('category')->getData()->getName();
                $name = $form->get('name')->getData();
                $slug = new Slugify;
                $fileSavePath = 'product_images/' . $slug-> slugify($category) . '/' . $slug-> slugify($name);
                $uploadedThumbnail = $form->get('thumbnail_file')->getData();
                foreach ($uploadedFiles as $index => $uploadedFile) {
                    $isThumbnail = $index === 0; 
                    $newFilename = $uploaderHelper->uploadProductImage($uploadedFile, $fileSavePath,  $isThumbnail);

                    if ($isThumbnail) {
                        if ($uploadedThumbnail) {
                            $newThumbnailFilename = $uploaderHelper->uploadProductImage($uploadedThumbnail, $fileSavePath,  $isThumbnail);
                            $product->setThumbnail($slug-> slugify($category) . '/' . $slug-> slugify($name) . '/thumb_' . $newThumbnailFilename);
                        } else {
                            $product->setThumbnail($slug-> slugify($category) . '/' . $slug-> slugify($name) . '/thumb_' . $newFilename);
                        }
                    }

                    $imagePaths[] = $slug-> slugify($category) . '/' . $slug-> slugify($name) . '/' . $newFilename;
                }
            }
            $product->setImage($imagePaths);
            $entityManager->persist($product);
            $entityManager->flush();


            $this->addFlash('success', 'Product ' . $product->getName() . ' updated successfully.');
            return $this->redirectToRoute('app_product', [
                'active_section' => 'Products',
            ]);
        }

        
        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'active_section' => 'Products',
        ]);
    }

    #[Route('/admin/product/delete/{slug}', name: 'app_product_delete', methods:['POST'])]
    public function delete(EntityManagerInterface $entityManager, #[MapEntity(mapping: ['slug' => 'slug'])] Product $product, UploaderHelper $uploaderHelper): Response
    {   
        $imagesPaths = $product->getImage();
        $thumbnail = $product->getThumbnail();
        if($imagesPaths)
        {   
            foreach ($imagesPaths as $image) {
                $uploaderHelper->deleteProductImage($image);
            }
        }
        if($thumbnail)
        {   
            $uploaderHelper->deleteProductImage($thumbnail);
        }
        $entityManager->remove($product);

        $entityManager->flush();

        $this->addFlash('success', 'Product ' . $product->getName() . ' deleted successfully.');
        return $this->redirectToRoute('app_product', [
            'active_section' => 'Products',
        ]);
    }

    #[Route('/admin/reviews', name: 'app_admin_reviews_index' , methods:["GET"])]
    public function reviewsIndex(EntityManagerInterface $entityManager) {
        $reviews = $entityManager->getRepository(Review::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/reviews/index.html.twig', [
            'reviews' => $reviews,
            'active_section' => 'Products',
        ]);
    }


    #[Route('/admin/product/reviews/{slug}', name: 'app_admin_manage_reviews', methods:["GET", "POST"])]
    public function manageReviews(
        #[MapEntity(mapping:['slug', 'slug'])] Product $product,
        Request $request,
        EntityManagerInterface $entityManager,
        string $slug
    ): Response {
        // Create a new review instance and set its product relationship
        $review = new Review();
        $review->setProduct($product);

        // Create the form using the AdminReviewType
        $form = $this->createForm(AdminReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persist the new review to the database
            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Review successfully added.');
            
            // Redirect to the same page to show the new review and a fresh form
            return $this->redirectToRoute('app_admin_manage_reviews', [
                'active_section' => 'product',
                'slug' => $product->getSlug()
            ]);
        }

        return $this->render('admin/reviews/review_management.html.twig', [
            'product' => $product,
            'review_form' => $form->createView(),
            'active_section' => 'product',
        ]);
    }

    #[Route('/admin/product/reviews/edit/{id}', name: 'app_admin_edit_review', methods:["GET", "POST"])]
    public function editReview(
        Review $review,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Find the product associated with the review to use for the redirect
        $product = $review->getProduct();

        // Create the form to edit the existing review
        $form = $this->createForm(AdminReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Persist the changes to the review
            $entityManager->flush();

            $this->addFlash('success', 'Review successfully updated.');

            // Redirect back to the review management page for the product
            return $this->redirectToRoute('app_admin_manage_reviews', [
                'slug' => $product->getSlug(),
                'active_section' => 'product'
            ]);
        }

        return $this->render('admin/reviews/review_management.html.twig', [
            'product' => $product,
            'review_form' => $form->createView(),
            'active_section' => 'product'
        ]);
    }

    #[Route('/admin/product/reviews/delete/{id}', name: 'app_admin_delete_review', methods:["POST"])]
    public function deleteReview(
        Review $review,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Check for a valid CSRF token
        if ($this->isCsrfTokenValid('delete' . $review->getId(), $request->request->get('_token'))) {
            // Find the product associated with the review for the redirect
            $product = $review->getProduct();
            
            $entityManager->remove($review);
            $entityManager->flush();

            $this->addFlash('success', 'Review successfully deleted.');
        }

        // Redirect back to the review management page
        return $this->redirectToRoute('app_admin_manage_reviews', [
                'slug' => $product->getSlug(),
                'active_section' => 'product'
        ]);
    }
}
