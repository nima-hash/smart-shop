<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Sale;
use App\Repository\CategoryRepository;
use App\Repository\OrderItemRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\SaleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;






#[Route('/admin/reports')]
class AdminReportController extends AbstractController
{

    #[Route('/', name: 'app_admin_report_index', methods: ['GET'])]
    public function index(SaleRepository $saleRepository, CategoryRepository $categoryRepository): Response
    {
        // Fetch all sales to display in the reports list
        $sales = $saleRepository->findAll();
        $categories = $categoryRepository->findAll();

        return $this->render('admin/report/index.html.twig', [
            'sales' => $sales,
            'active_section' => 'Reports',
            'categories' => $categories
        ]);
    }

    #[Route('/sale/{id}', name: 'app_admin_report_sale', methods: ['GET'])]
    public function showSaleReport(Sale $sale, OrderItemRepository $orderItemRepository): Response
    {
        $now = new \DateTime();
        $isStarted = $sale->getStartDate() <= $now;

        $totalSoldItems = 0;
        $totalSoldPrice = 0;
        $soldCounts = [];

        // Only perform calculations if the sale has started
        if ($isStarted) {
            $productIds = $sale->getProducts()->map(fn($product) => $product->getId())->toArray();

            if (!empty($productIds)) {
                // Find all sold items for the products in this sale since its start date
                $soldItems = $orderItemRepository->createQueryBuilder('si')
                    ->where('si.product IN (:productIds)')
                    ->andWhere('si.createdAt >= :startDate')
                    ->setParameter('productIds', $productIds)
                    ->setParameter('startDate', $sale->getStartDate())
                    ->getQuery()
                    ->getResult();

                // Loop through the sold items to calculate totals and per-product counts
                foreach ($soldItems as $soldItem) {
                    $totalSoldItems += $soldItem->getQuantity();
                    $totalSoldPrice += $soldItem->getQuantity() * $soldItem->getPriceAtPurchase();

                    $productId = $soldItem->getProduct()->getId();
                    if (!isset($soldCounts[$productId])) {
                        $soldCounts[$productId] = 0;
                    }
                    $soldCounts[$productId] += $soldItem->getQuantity();
                }
            }
        }
        
        return $this->render('admin/report/sale_report.html.twig', [
            'sale' => $sale,
            'isStarted' => $isStarted,
            'totalSoldItems' => $totalSoldItems,
            'totalSoldPrice' => $totalSoldPrice,
            'soldCounts' => $soldCounts,
            'active_section' => 'Reports'
        ]);
    }

    #[Route('/generate', name: 'app_admin_report_generate', methods: ['GET'])]
    public function generateCustomReport(Request $request, OrderItemRepository $orderItemRepository, EntityManagerInterface $entityManagerInterface): Response
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $categoryId = $request->query->get('category');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');


        
        // Build a dynamic query to filter the sold items
        $queryBuilder = $orderItemRepository->createQueryBuilder('oi')
            ->leftJoin('oi.product', 'p')
            ->addSelect('p');
        
        if ($startDate) {
            $queryBuilder->andWhere('oi.createdAt >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('oi.createdAt <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'));
        }

        if ($categoryId) {
            $category = $entityManagerInterface->getRepository(Category::class)->find($categoryId);
            if ($category) {
                $queryBuilder->andWhere('oi.category = :category')
                ->setParameter('category', $category);
            }
        }

        if ($minPrice) {
            $queryBuilder->andWhere('oi.priceAtPurchase >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice) {
            $queryBuilder->andWhere('oi.priceAtPurchase <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }
        $queryBuilder->orderBy('oi.createdAt', 'DESC');

        $soldItems = $queryBuilder->getQuery()->getResult();
        return $this->render('admin/report/custom_report.html.twig', [
            'soldItems' => $soldItems,
            'filters' => $request->query->all(),
            'active_section' => 'Reports',
        ]);
    }
}
