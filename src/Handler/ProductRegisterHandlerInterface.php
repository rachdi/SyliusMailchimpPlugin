<?php

namespace Odiseo\SyliusMailchimpPlugin\Handler;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;

interface ProductRegisterHandlerInterface
{
    /**
     * @param ProductInterface $product
     * @param ChannelInterface $channel
     *
     * @return array|false
     */
    public function register(ProductInterface $product, ChannelInterface $channel);

    /**
     * @param ProductInterface $product
     * @param ChannelInterface $channel
     *
     * @return array|false
     */
    public function unregister(ProductInterface $product, ChannelInterface $channel);
}