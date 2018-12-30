<?php

declare(strict_types=1);

namespace Odiseo\SyliusMailchimpPlugin\Handler;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Odiseo\SyliusMailchimpPlugin\Api\EcommerceInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ProductRegisterHandler implements ProductRegisterHandlerInterface
{
    /**
     * @var EcommerceInterface
     */
    private $ecommerceApi;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @param EcommerceInterface $ecommerceApi
     * @param RouterInterface $router
     * @param CacheManager $cacheManager
     */
    public function __construct(
        EcommerceInterface $ecommerceApi,
        RouterInterface $router,
        CacheManager $cacheManager
    )
    {
        $this->ecommerceApi = $ecommerceApi;
        $this->router = $router;
        $this->cacheManager = $cacheManager;
    }

    /**
     * @inheritdoc
     */
    public function register(ProductInterface $product, ChannelInterface $channel)
    {
        $productId = (string)$product->getId();
        $storeId = $channel->getCode();

        $response = $this->ecommerceApi->getProduct($storeId, $productId);
        $isNew = !isset($response['id']);

        $variants = [];
        /** @var ProductVariant $productVariant */
        foreach ($product->getVariants() as $productVariant) {
            $variant = [
                'id' => (string)$productVariant->getId(),
                'title' => $productVariant->getName()?$productVariant->getName():$product->getName(),
            ];

            if ($variantPrice = $this->getVariantPrice($productVariant, $channel)) {
                $variant['price'] = $variantPrice;
            }

            if ($productVariant->isTracked()) {
                $variant['inventory_quantity'] = $productVariant->getOnHand();
            }

            $variants[] = $variant;
        }


        $productImages = [];
        /** @var ProductImageInterface $image */
        foreach ($product->getImages() as $image) {
            $productImages[] = [
                'id' => (string) $image->getId(),
                'url' => $this->getImageUrl($image, $channel),
            ];
        }

        $data = [
            'id' => $productId,
            'title' => $product->getName(),
            'url' => $this->getProductUrl($product, $channel),
            'description' => $product->getDescription()?:'',
            'images' => $productImages,
            'variants' => $variants,
        ];

        if (count($productImages) > 0) {
            $data['image_url'] = $productImages[0]['url'];
        }

        if ($isNew) {
            $response = $this->ecommerceApi->addProduct($storeId, $data);
        } else {
            $response = $this->ecommerceApi->updateProduct($storeId, $productId, $data);
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function unregister(ProductInterface $product, ChannelInterface $channel)
    {
        $productId = (string)$product->getId();
        $storeId = $channel->getCode();

        $response = $this->ecommerceApi->getProduct($storeId, $productId);
        $isNew = !isset($response['id']);

        if (!$isNew) {
            return $this->ecommerceApi->removeProduct($storeId, $productId);
        }

        return false;
    }

    /**
     * @param ProductVariant $variant
     * @param ChannelInterface $channel
     *
     * @return int|null
     */
    protected function getVariantPrice(ProductVariant $variant, ChannelInterface $channel)
    {
        /** @var ChannelPricingInterface $channelPricing */
        $channelPricing = $variant->getChannelPricingForChannel($channel);

        return $channelPricing ? $channelPricing->getPrice() : null;
    }

    /**
     * @param ProductInterface $product
     * @param ChannelInterface $channel
     *
     * @return string
     */
    protected function getProductUrl(ProductInterface $product, ChannelInterface $channel): string
    {
        $context = $this->router->getContext();
        $context->setHost($channel->getHostname());

        $locale = 'en';
        if ($channel->getDefaultLocale()) {
            $locale = $channel->getDefaultLocale()->getCode();
        } else {
            if (count($channel->getLocales()) > 0) {
                $locale = $channel->getLocales()->first()->getCode();
            }
        }

        $product->setCurrentLocale($locale);

        return $this->router->generate('sylius_shop_product_show', [
            '_locale' => $locale,
            'slug' => $product->getSlug()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param ProductImageInterface $image
     * @param ChannelInterface $channel
     *
     * @return string
     */
    protected function getImageUrl(ProductImageInterface $image, ChannelInterface $channel): string
    {
        $context = $this->router->getContext();
        $context->setHost($channel->getHostname());

        return $this->cacheManager->generateUrl($image->getPath(), 'sylius_large');
    }
}
