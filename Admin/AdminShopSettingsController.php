<?php

namespace App\Controller\Admin;

use App\Entity\ShopSettings;
use App\Form\ShopSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;


#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class AdminShopSettingsController extends AbstractController
{

    public function __construct(
        private SluggerInterface $slugger,
        private Filesystem $filesystem
    ) {}

    #[Route('/', name: 'app_admin_settings_index')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Fetch the single settings entity, or create a new one if it doesn't exist.
        $settings = $entityManager->getRepository(ShopSettings::class)->findOneBy([]);
        if (!$settings) {
            $settings = new ShopSettings();
            $entityManager->persist($settings);
            $entityManager->flush();
        }

        $shippingOptions = $settings->getShippingOptions();
        if (is_string($shippingOptions)) {
            $decodedOptions = json_decode($shippingOptions, true);
            // Check if decoding was successful and it's an array
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedOptions)) {
                $settings->setShippingOptions($decodedOptions);
            } else {
                $settings->setShippingOptions([]);
            }
        }

        $form = $this->createForm(ShopSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file uploads
            $this->handleFileUploads($form, $settings);

            $entityManager->flush();
            $this->addFlash('success', 'Shop settings updated successfully.');

            return $this->redirectToRoute('app_admin_settings_index');
        }

        return $this->render('admin/settings/index.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings, // Pass the settings entity to the view
            'active_section' => 'Settings',
        ]);
    }

    /**
     * Reverts all settings to their default values.
     */
    #[Route('/revert', name: 'app_admin_settings_revert', methods: ['POST'])]
    public function revertToDefaults(EntityManagerInterface $entityManager): Response
    {
        $settings = $entityManager->getRepository(ShopSettings::class)->findOneBy([]);
        if (!$settings) {
            $this->addFlash('error', 'No settings found to revert.');
            return $this->redirectToRoute('app_admin_settings_index');
        }

        // Create a new entity to get the default values from the constructor
        $defaultSettings = new ShopSettings();

        // Reset each property to its default value
        $settings->setShopName($defaultSettings->getShopName());
        $settings->setTagline($defaultSettings->getTagline());
        $settings->setShopEmail($defaultSettings->getShopEmail());
        $settings->setPhone($defaultSettings->getPhone());
        $settings->setAddress($defaultSettings->getAddress());
        $settings->setSocialMedia($defaultSettings->getSocialMedia());
        $settings->setCurrency($defaultSettings->getCurrency());
        $settings->setTaxRate($defaultSettings->getTaxRate());
        $settings->setHomePageBannerImage($defaultSettings->getHomePageBannerImage());
        $settings->setShippingOptions($defaultSettings->getShippingOptions());
        $settings->setHeroImageHeading($defaultSettings->getHeroImageHeading());
        $settings->setHeroImageTagline($defaultSettings->getHeroImageTagline());
        $settings->setSaleBannerImage($defaultSettings->getSaleBannerImage());
        $settings->setSaleImageHeading($defaultSettings->getSaleImageHeading());
        $settings->setSaleImageTagline($defaultSettings->getSaleImageTagline());
        $settings->setFavIcon($defaultSettings->getFavIcon());
        $settings->setLogo($defaultSettings->getLogo());
        $settings->setTheme($defaultSettings->getTheme());
        $settings->setProductsPerPage($defaultSettings->getProductsPerPage());
        $settings->setFeaturedCategories($defaultSettings->getFeaturedCategories());
        $settings->setFreeShippingThreshold($defaultSettings->getFreeShippingThreshold());
        $settings->setAllowedPaymentMethods($defaultSettings->getAllowedPaymentMethods());
        $settings->setAllowedCountries($defaultSettings->getAllowedCountries());
        $settings->setMetaTitle($defaultSettings->getMetaTitle());
        $settings->setMetaDescription($defaultSettings->getMetaDescription());
        $settings->setTermsAndConditionsUrl($defaultSettings->getTermsAndConditionsUrl());
        $settings->setPrivacyUrl($defaultSettings->getPrivacyUrl());
        $settings->setCookieConsentText($defaultSettings->getCookieConsentText());
        
        $entityManager->flush();
        $this->addFlash('success', 'Shop settings have been reverted to default values.');

        return $this->redirectToRoute('app_admin_settings_index');
    }

    /**
     * Handles the upload of files for the shop settings.
     */
private function handleFileUploads(FormInterface $form, ShopSettings $settings): void
{
    // Handle Logo Upload
    $logoFile = $form->get('logo')->getData();
    if ($logoFile) {
        $newFilename = md5(uniqid()) . '.' . $logoFile->guessExtension();
        $logoFile->move(
            $this->getParameter('shop_settings_uploads_directory'),
            $newFilename
        );
        $settings->setLogo($newFilename);
    }
    // No 'else' block here. If $logoFile is null, the existing logo is not overwritten.

    // Handle FavIcon Upload
    $favIconFile = $form->get('favIcon')->getData();
    if ($favIconFile) {
        $newFilename = md5(uniqid()) . '.' . $favIconFile->guessExtension();
        $favIconFile->move(
            $this->getParameter('shop_settings_uploads_directory'),
            $newFilename
        );
        $settings->setFavIcon($newFilename);
    }

    // Handle Banner Image Upload
    $bannerFile = $form->get('homePageBannerImage')->getData();
    if ($bannerFile) {
        $newFilename = md5(uniqid()) . '.' . $bannerFile->guessExtension();
        $bannerFile->move(
            $this->getParameter('shop_settings_uploads_directory'),
            $newFilename
        );
        $settings->setHomePageBannerImage($newFilename);
    }
}
}
