<?php

namespace AssetManager\Resolver;

use Assetic\Asset\FileAsset;
use Assetic\Factory\Resource\DirectoryResource;
use AssetManager\Exception;
use AssetManager\Service\MimeResolver;
use SplFileInfo;
use Zend\Db\TableGateway\Exception\RuntimeException;
use Zend\Stdlib\SplStack;

/**
 * This resolver allows you to resolve from a stack of translation (preg_replace) expressions to a path.
 */
class TranslatePathStackResolver implements ResolverInterface, MimeResolverAwareInterface
{
    const MATCH_KEY = 'match';
    const PATTERN_KEY = 'pattern';
    const REPLACEMENT_KEY = 'replacement';

    /**
     * @var Array
     */
    protected $translatables = array();

    /**
     * Flag indicating whether or not LFI protection for rendering view scripts is enabled
     *
     * @var bool
     */
    protected $lfiProtectionOn = true;

    /**
     * The mime resolver.
     *
     * @var MimeResolver
     */
    protected $mimeResolver;

    /**
     * Constructor
     *
     * Populate the array stack with a list of aliases and their corresponding paths
     *
     * @param  array                              $translatables
     * @throws Exception\InvalidArgumentException
     */
    public function __construct(Array $translatables)
    {
        foreach ($translatables as $key => $config) {
            $this->addTranslatable($key, $config);
        }
    }

    /**
     * Add a single translatable to the stack
     *
     * @param  string                             $key
     * @param  array                             $config
     * @throws Exception\InvalidArgumentException
     */
    private function addTranslatable($key, $config)
    {
        if (!is_string($key)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid key provided (used to identify each translation); must be a string, received %s',
                gettype($key)
            ));
        }

        if (!is_array($config)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid configuration provided; must be an array, received %s',
                gettype($config)
            ));
        }

        $configFields = [
            self::MATCH_KEY,
            self::PATTERN_KEY,
            self::REPLACEMENT_KEY,
        ];

        foreach($configFields as $field){
            if (!isset($config[$field])) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Invalid configuration provided; \'^s\' must be set in configuration.',
                    $field
                ));
            }
        }

        $this->translatables[$key] = $config;
    }

    /**
     * Normalize a path for insertion in the stack
     *
     * @param  string $path
     * @return string
     */
    private function normalizePath($path)
    {
        return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Set the mime resolver
     *
     * @param MimeResolver $resolver
     */
    public function setMimeResolver(MimeResolver $resolver)
    {
        $this->mimeResolver = $resolver;
    }

    /**
     * Get the mime resolver
     *
     * @return MimeResolver
     */
    public function getMimeResolver()
    {
        return $this->mimeResolver;
    }

    /**
     * Set LFI protection flag
     *
     * @param  bool $flag
     * @return self
     */
    public function setLfiProtection($flag)
    {
        $this->lfiProtectionOn = (bool) $flag;
    }

    /**
     * Return status of LFI protection flag
     *
     * @return bool
     */
    public function isLfiProtectionOn()
    {
        return $this->lfiProtectionOn;
    }

    /**
     * {@inheritDoc}
     */
    public function resolve($name)
    {
        if ($this->isLfiProtectionOn() && preg_match('#\.\.[\\\/]#', $name)) {
            return null;
        }

        foreach ($this->translatables as $key => $config) {
            $match = $config[self::MATCH_KEY];
            $replacement = $config[self::REPLACEMENT_KEY];
            $pattern = $config[self::PATTERN_KEY];

            $matches = array();
            preg_match($match, $name, $matches);
            if(count($matches) == 0){
                continue;
            }

            $path = preg_replace($pattern, $replacement, $name);
            $file = new SplFileInfo($path);

            if ($file->isReadable() && !$file->isDir()) {
                $filePath = $file->getRealPath();
                $mimeType = $this->getMimeResolver()->getMimeType($filePath);
                $asset    = new FileAsset($filePath);

                $asset->mimetype = $mimeType;

                return $asset;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        die(__METHOD__);
        $collection = array();

        foreach ($this->translatables as $match => $path) {
            $locations = new SplStack();
            $pathInfo = new SplFileInfo($path);
            $locations->push($pathInfo);
            $basePath = $this->normalizePath($pathInfo->getRealPath());

            while (!$locations->isEmpty()) {
                /** @var SplFileInfo $pathInfo */
                $pathInfo = $locations->pop();
                if (!$pathInfo->isReadable()) {
                    throw new RuntimeException(sprintf('%s is not readable.', $pathInfo->getPath()));
                }
                if ($pathInfo->isDir()) {
                    foreach (new DirectoryResource($pathInfo->getRealPath()) as $resource) {
                        $locations->push(new SplFileInfo($resource));
                    }
                } else {
                    $collection[] = $alias . substr($pathInfo->getRealPath(), strlen($basePath));
                }
            }
        }

        return array_unique($collection);
    }
}
