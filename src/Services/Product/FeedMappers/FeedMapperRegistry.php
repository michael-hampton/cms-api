<?php

namespace App\Services\Product\FeedMappers;

class FeedMapperRegistry
{
    /**
     * @var FeedMapperInterface[]
     */
    protected array $mappers = [];

    /**
     * @var FeedMapperInterface
     */
    protected FeedMapperInterface $defaultMapper;

    public function __construct()
    {
        $this->defaultMapper = new DefaultFeedMapper();
    }

    /**
     * Register a feed mapper
     *
     * @param FeedMapperInterface $mapper
     * @return self
     */
    public function register(FeedMapperInterface $mapper): self
    {
        $this->mappers[] = $mapper;
        return $this;
    }

    /**
     * Get appropriate mapper for the given URL and merchant
     *
     * @param string $url
     * @param int $merchantId
     * @return FeedMapperInterface
     */
    public function getMapper(string $url, int $merchantId): FeedMapperInterface
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->supports($url, $merchantId)) {
                return $mapper;
            }
        }

        return $this->defaultMapper;
    }

    /**
     * Set default mapper
     *
     * @param FeedMapperInterface $mapper
     * @return self
     */
    public function setDefaultMapper(FeedMapperInterface $mapper): self
    {
        $this->defaultMapper = $mapper;
        return $this;
    }
}