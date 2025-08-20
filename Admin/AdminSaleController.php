<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\Sale;
use App\Form\SaleType;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;
use phpDocumentor\Reflection\Types\Integer;

#[Route('/admin/sales')]
class AdminSaleController extends AbstractController
{
    /**
     * Renders a list of all sales.
     */
    #[Route('/', name: 'app_admin_sale_index', methods: ['GET'])]
    public function index(SaleRepository $saleRepository): Response
    {
        return $this->render('admin/sales/index.html.twig', [
            'sales' => $saleRepository->findAll(),
            'active_section' => 'Sales',
        ]);
    }

    /**
     * Handles the creation of a new sale.
     */
    #[Route('/new', name: 'app_admin_sale_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sale = new Sale();
        $form = $this->createForm(SaleType::class, $sale);
        $form->handleRequest($request);
        $products = $entityManager->getRepository(Product::class)->findAll();

        if ($form->isSubmitted() && $form->isValid()) {

            $productSelectionData = $form->get('products')->getData();
            
            foreach ($productSelectionData as $productId ) {
                $product = $entityManager->getRepository(Product::class)->findOneBy(['id' => $productId]);
                if ($product) {
                    $product->setDiscountPercentage($form->get('discountPercentage')->getData());
                }
            }
            
            $entityManager->persist($sale);
            $entityManager->flush();

            $this->addFlash('success', 'The new sale has been created successfully!');

            return $this->redirectToRoute('app_admin_sale_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/sales/new.html.twig', [
            'sale' => $sale,
            'form' => $form,
            'products' => $products,
            'selected_products' => [],
            'active_section' => 'Sales',
        ]);
    }

    /**
     * Displays a single sale's details.
     */
    #[Route('/{id}', name: 'app_admin_sale_show', methods: ['GET'])]
    public function show(Sale $sale): Response
    {
        return $this->render('admin/sales/show.html.twig', [
            'sale' => $sale,
            'active_section' => 'Sales',
        ]);
    }

    /**
     * Handles the editing of an existing sale.
     */
    #[Route('/{id}/edit', name: 'app_admin_sale_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sale $sale, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SaleType::class, $sale);
        $form->handleRequest($request);
        $selectedProducts = $sale -> getProducts();
        $products = $entityManager->getRepository(Product::class)->findAll();
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'The sale has been updated successfully!');

            return $this->redirectToRoute('app_admin_sale_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/sales/edit.html.twig', [
            'sale' => $sale,
            'form' => $form,
            'products' => $products,
            'selected_products' => $selectedProducts,
            'active_section' => 'Sales',
        ]);
    }

    /**
     * Deletes a sale.
     */
    #[Route('/{id}', name: 'app_admin_sale_delete', methods: ['POST'])]
    public function delete(Request $request, Sale $sale, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $sale->getId(), $request->request->get('_token'))) {
            $entityManager->remove($sale);
            $entityManager->flush();
        }

        $this->addFlash('success', 'The sale has been deleted successfully!');

        return $this->redirectToRoute('app_admin_sale_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/products', name: 'app_admin_sale_products', methods: ['GET'])]
    public function getProducts(Request $request, EntityManagerInterface $entityManager, PaginatorInterface $paginator): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        $priceFilter = $request->query->get('price', '');
        $createdAt = $request->query->get('createdAt', '');

        $productRepository = $entityManager->getRepository(Product::class);
        $queryBuilder = $productRepository->createQueryBuilder('p');

        // Apply search filter
        if (!empty($search)) {
            $queryBuilder->andWhere('p.name LIKE :search')
                         ->setParameter('search', '%' . $search . '%');
        }

        // Apply category filter
        if (!empty($category)) {
            $queryBuilder->andWhere('p.category = :category')
                         ->setParameter('category', $category);
        }

        // Apply price filter 
        if ($priceFilter === 'low') {
            $queryBuilder->andWhere('p.price < 50');
        } elseif ($priceFilter === 'high') {
            $queryBuilder->andWhere('p.price >= 50');
        }

        // Apply creation date filter
        if (!empty($createdAt)) {
            $queryBuilder->andWhere('p.createdAt >= :createdAt')
                         ->setParameter('createdAt', new \DateTime($createdAt));
        }

        // Paginate the query using KnpPaginator
        $pagination = $paginator->paginate(
            $queryBuilder->getQuery(),
            $page,
            $limit
        );

        // Prepare product data for JSON response
        $products = [];
        foreach ($pagination->getItems() as $product) {
            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'category' => $product->getCategory(),
                'stock' => $product->getStock(),
                'thumbnail' => $product->getThumbnail(),
            ];
        }

        return new JsonResponse([
            'products' => $products,
            'totalItems' => $pagination->getTotalItemCount(),
            'totalPages' => ceil($pagination->getTotalItemCount() / $limit),
            'currentPage' => $pagination->getCurrentPageNumber(),
        ]);
    }
}
