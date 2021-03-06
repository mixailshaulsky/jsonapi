<?php
/*
 * This file is part of the reva2/jsonapi.
 *
 * (c) Sergey Revenko <dedsemen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Reva2\JsonApi\Decoders\Mapping\Loader;

use Doctrine\Common\Annotations\Reader;
use Reva2\JsonApi\Annotations\Attribute;
use Reva2\JsonApi\Annotations\ApiDocument;
use Reva2\JsonApi\Annotations\Id;
use Reva2\JsonApi\Annotations\ApiResource;
use Reva2\JsonApi\Annotations\ApiObject;
use Reva2\JsonApi\Annotations\Content as ApiContent;
use Reva2\JsonApi\Annotations\Loader;
use Reva2\JsonApi\Annotations\Metadata;
use Reva2\JsonApi\Annotations\Property;
use Reva2\JsonApi\Annotations\Relationship;
use Reva2\JsonApi\Annotations\VirtualAttribute;
use Reva2\JsonApi\Annotations\VirtualProperty;
use Reva2\JsonApi\Annotations\VirtualRelationship;
use Reva2\JsonApi\Contracts\Decoders\Mapping\Loader\LoaderInterface;
use Reva2\JsonApi\Contracts\Decoders\Mapping\ObjectMetadataInterface;
use Reva2\JsonApi\Decoders\Mapping\ClassMetadata;
use Reva2\JsonApi\Decoders\Mapping\DocumentMetadata;
use Reva2\JsonApi\Decoders\Mapping\ObjectMetadata;
use Reva2\JsonApi\Decoders\Mapping\PropertyMetadata;
use Reva2\JsonApi\Decoders\Mapping\ResourceMetadata;

/**
 * Loads JSON API metadata using a Doctrine annotations
 *
 * @package Reva2\JsonApi\Decoders\Mapping\Loader
 * @author Sergey Revenko <dedsemen@gmail.com>
 */
class AnnotationLoader implements LoaderInterface
{
    /**
     * @var Reader
     */
    protected $reader;

    /**
     * Constructor
     *
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * @inheritdoc
     */
    public function loadClassMetadata(\ReflectionClass $class)
    {
        if (interface_exists('Doctrine\ORM\Proxy\Proxy') && $class->implementsInterface('Doctrine\ORM\Proxy\Proxy')) {
            return $this->loadClassMetadata($class->getParentClass());
        }

        if (null !== ($resource = $this->reader->getClassAnnotation($class, ApiResource::class))) {
            /* @var $resource ApiResource */
            return $this->loadResourceMetadata($resource, $class);
        } elseif (null !== ($document = $this->reader->getClassAnnotation($class, ApiDocument::class))) {
            /* @var $document ApiDocument */
            return $this->loadDocumentMetadata($document, $class);
        } else {
            $object = $this->reader->getClassAnnotation($class, ApiObject::class);

            return $this->loadObjectMetadata($class, $object);
        }
    }

    /**
     * Parse JSON API resource metadata
     *
     * @param ApiResource $resource
     * @param \ReflectionClass $class
     * @return ResourceMetadata
     */
    private function loadResourceMetadata(ApiResource $resource, \ReflectionClass $class)
    {
        $metadata = new ResourceMetadata($class->name);
        $metadata->setName($resource->name);
        $metadata->setLoader($resource->loader);

        $properties = $class->getProperties();
        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            foreach ($this->reader->getPropertyAnnotations($property) as $annotation) {
                if ($annotation instanceof Attribute) {
                    $metadata->addAttribute($this->loadPropertyMetadata($annotation, $property));
                } elseif ($annotation instanceof Relationship) {
                    $metadata->addRelationship($this->loadPropertyMetadata($annotation, $property));
                } elseif ($annotation instanceof Id) {
                    $metadata->setIdMetadata($this->loadPropertyMetadata($annotation, $property));
                }
            }
        }

        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            foreach ($this->reader->getMethodAnnotations($method) as $annotation) {
                if ($annotation instanceof VirtualAttribute) {
                    $metadata->addAttribute($this->loadVirtualMetadata($annotation, $method));
                } else if ($annotation instanceof VirtualRelationship) {
                    $metadata->addRelationship($this->loadVirtualMetadata($annotation, $method));
                }
            }
        }

        $this->loadDiscriminatorMetadata($resource, $metadata);

        return $metadata;
    }

    /**
     * @param \ReflectionClass $class
     * @param ApiObject|null $object
     * @return ObjectMetadata
     */
    private function loadObjectMetadata(\ReflectionClass $class, ApiObject $object = null)
    {
        $metadata = new ObjectMetadata($class->name);

        $properties = $class->getProperties();
        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            $annotation = $this->reader->getPropertyAnnotation($property, Property::class);
            /* @var $annotation Property */
            if (null === $annotation) {
                continue;
            }

            $metadata->addProperty($this->loadPropertyMetadata($annotation, $property));
        }

        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            if ($method->getDeclaringClass()->name !== $class->name) {
                continue;
            }

            $annotation = $this->reader->getMethodAnnotation($method, VirtualProperty::class);
            /* @var $annotation VirtualProperty */
            if ($annotation === null) {
                continue;
            }

            $metadata->addProperty($this->loadVirtualMetadata($annotation, $method));
        }

        if (null !== $object) {
            $this->loadDiscriminatorMetadata($object, $metadata);
        }

        return $metadata;
    }

    /**
     * Parse JSON API document metadata
     *
     * @param ApiDocument $document
     * @param \ReflectionClass $class
     * @return DocumentMetadata
     */
    private function loadDocumentMetadata(ApiDocument $document, \ReflectionClass $class)
    {
        $metadata = new DocumentMetadata($class->name);
        $metadata->setAllowEmpty($document->allowEmpty);

        $properties = $class->getProperties();
        foreach ($properties as $property) {
            if ($property->getDeclaringClass()->name !== $class->name) {
                continue;
            }


            foreach ($this->reader->getPropertyAnnotations($property) as $annotation) {
                if ($annotation instanceof ApiContent) {
                    $metadata->setContentMetadata($this->loadPropertyMetadata($annotation, $property));
                } elseif ($annotation instanceof Metadata) {
                    $metadata->setMetadata($this->loadPropertyMetadata($annotation, $property));
                }
            }
        }

        return $metadata;
    }

    /**
     * Parse property metadata
     *
     * @param Property $annotation
     * @param \ReflectionProperty $property
     * @return PropertyMetadata
     */
    private function loadPropertyMetadata(Property $annotation, \ReflectionProperty $property)
    {
        $metadata = new PropertyMetadata($property->name, $property->class);

        list($dataType, $dataTypeParams) = $this->parseDataType($annotation, $property);

        $metadata
            ->setDataType($dataType)
            ->setDataTypeParams($dataTypeParams)
            ->setDataPath($this->getDataPath($annotation, $property))
            ->setConverter($annotation->converter)
            ->setGroups($annotation->groups)
            ->setLoaders($this->parseLoaders($annotation->loaders));

        if ($annotation->setter) {
            $metadata->setSetter($annotation->setter);
        } elseif (false === $property->isPublic()) {
            $setter = 'set' . ucfirst($property->name);
            if (false === $property->getDeclaringClass()->hasMethod($setter)) {
                throw new \RuntimeException(sprintf(
                    "Couldn't find setter for non public property: %s:%s",
                    $property->class,
                    $property->name
                ));
            }

            $metadata->setSetter($setter);
        }

        return $metadata;
    }

    /**
     * Parse virtual property metadata
     *
     * @param VirtualProperty $annotation
     * @param \ReflectionMethod $method
     * @return PropertyMetadata
     */
    private function loadVirtualMetadata(VirtualProperty $annotation, \ReflectionMethod $method)
    {
        if (empty($annotation->name)) {
            throw new \InvalidArgumentException(sprintf(
                "Virtual property name not specified: %s:%s()",
                $method->class,
                $method->name
            ));
        }

        list($dataType, $dataTypeParams) = $this->parseVirtualDataType($annotation, $method);

        $metadata = new PropertyMetadata($annotation->name, $method->class);
        $metadata
            ->setDataType($dataType)
            ->setDataTypeParams($dataTypeParams)
            ->setDataPath($this->getVirtualDataPath($annotation, $method))
            ->setConverter($annotation->converter)
            ->setGroups($annotation->groups)
            ->setSetter($method->name);

        return $metadata;
    }

    /**
     * Parse property data type
     *
     * @param Property $annotation
     * @param \ReflectionProperty $property
     * @return array
     */
    private function parseDataType(Property $annotation, \ReflectionProperty $property)
    {
        if (!empty($annotation->parser)) {
            if (!$property->getDeclaringClass()->hasMethod($annotation->parser)) {
                throw new \InvalidArgumentException(sprintf(
                    "Custom parser function %s:%s() for property '%s' does not exist",
                    $property->class,
                    $annotation->parser,
                    $property->name
                ));
            }
            return ['custom', $annotation->parser];
        } elseif (!empty($annotation->type)) {
            return $this->parseDataTypeString($annotation->type);
        } elseif (preg_match('~@var\s(.*?)\s~si', $property->getDocComment(), $matches)) {
            return $this->parseDataTypeString($matches[1]);
        } else {
            return ['raw', null];
        }
    }

    /**
     * Parse virtual property data type
     *
     * @param VirtualProperty $annotation
     * @param \ReflectionMethod $method
     * @return array
     */
    private function parseVirtualDataType(VirtualProperty $annotation, \ReflectionMethod $method)
    {
        if (!empty($annotation->parser)) {
            if (!$method->getDeclaringClass()->hasMethod($annotation->parser)) {
                throw new \InvalidArgumentException(sprintf(
                    "Custom parser function %s:%s() for virtual property '%s' does not exist",
                    $method->class,
                    $annotation->parser,
                    $annotation->name
                ));
            }
            return ['custom', $annotation->parser];
        } elseif (!empty($annotation->type)) {
            return $this->parseDataTypeString($annotation->type);
        } else {
            return ['raw', null];
        }
    }

    /**
     * Parse data type string
     *
     * @param string $type
     * @return array
     */
    private function parseDataTypeString($type)
    {
        $params = null;

        if ('raw' === $type) {
            $dataType = 'raw';
            $params = null;
        } elseif ($this->isScalarDataType($type)) {
            $dataType = 'scalar';
            $params = $type;
        } elseif (preg_match('~^DateTime(<(.*?)>)?$~', $type, $matches)) {
            $dataType = 'datetime';
            if (3 === count($matches)) {
                $params = $matches[2];
            }
        } elseif (
            (preg_match('~Array(<(.*?)>)?$~si', $type, $matches)) ||
            (preg_match('~^(.*?)\[\]$~si', $type, $matches))
        ) {
            $dataType = 'array';
            if (3 === count($matches)) {
                $params = $this->parseDataTypeString($matches[2]);
            } elseif (2 === count($matches)) {
                $params = $this->parseDataTypeString($matches[1]);
            } else {
                $params = ['raw', null];
            }
        } else {
            $type = ltrim($type, '\\');

            if (!class_exists($type)) {
                throw new \InvalidArgumentException(sprintf(
                    "Unknown object type '%s' specified",
                    $type
                ));
            }

            $dataType = 'object';
            $params = $type;
        }

        return [$dataType, $params];
    }

    /**
     * Returns true if specified type scalar. False otherwise.
     *
     * @param string $type
     * @return bool
     */
    private function isScalarDataType($type)
    {
        return in_array($type, ['string', 'bool', 'boolean', 'int', 'integer', 'float', 'double']);
    }

    /**
     * Load discriminator metadata
     *
     * @param ApiObject $object
     * @param ClassMetadata $metadata
     */
    private function loadDiscriminatorMetadata(ApiObject $object, ClassMetadata $metadata)
    {
        if (!$object->discField) {
            return;
        }

        $fieldMeta = null;
        $field = $object->discField;
        if ($metadata instanceof ObjectMetadataInterface) {
            $properties = $metadata->getProperties();
            if (array_key_exists($field, $properties)) {
                $fieldMeta = $properties[$field];
            }
        } elseif ($metadata instanceof ResourceMetadata) {
            $attributes = $metadata->getAttributes();
            if (array_key_exists($field, $attributes)) {
                $fieldMeta = $attributes[$field];
            }
        }

        if (null === $fieldMeta) {
            throw new \InvalidArgumentException("Specified discriminator field not found in object properties");
        } elseif (('scalar' !== $fieldMeta->getDataType()) || ('string' !== $fieldMeta->getDataTypeParams())) {
            throw new \InvalidArgumentException("Discriminator field must point to property that contain string value");
        }

        $metadata->setDiscriminatorField($fieldMeta);
        $metadata->setDiscriminatorMap($object->discMap);
        $metadata->setDiscriminatorError($object->discError);
    }

    /**
     * Returns data path
     *
     * @param Property $annotation
     * @param \ReflectionProperty $property
     * @return string
     */
    private function getDataPath(Property $annotation, \ReflectionProperty $property)
    {
        $prefix = '';
        $suffix = '';
        if ($annotation instanceof Attribute) {
            $prefix = 'attributes.';
        } elseif ($annotation instanceof Relationship) {
            $prefix = 'relationships.';
            $suffix = '.data';
        }

        if (!empty($prefix) || !empty($suffix)) {
            if (null !== $annotation->path) {
                return $prefix . $annotation->path . $suffix;
            }

            return $prefix . $property->name . $suffix;
        }

        return $annotation->path;
    }

    /**
     * Returns data path for virtual property
     *
     * @param VirtualProperty $annotation
     * @param \ReflectionMethod $method
     * @return string
     */
    private function getVirtualDataPath(VirtualProperty $annotation, \ReflectionMethod $method)
    {
        $prefix = '';
        $suffix = '';
        if ($annotation instanceof VirtualAttribute) {
            $prefix = 'attributes.';
        } elseif ($annotation instanceof VirtualRelationship) {
            $prefix = 'relationships.';
            $suffix = '.data';
        }

        if (!empty($prefix) || !empty($suffix)) {
            if (null !== $annotation->path) {
                return $prefix . $annotation->path . $suffix;
            }

            return $prefix . $annotation->name . $suffix;
        }

        return $annotation->path;
    }

    /**
     * Parse property loaders
     *
     * @param array|Loader[] $loaders
     * @return array
     */
    private function parseLoaders(array $loaders)
    {
        $propLoaders = [];

        foreach ($loaders as $loader) {
            if (array_key_exists($loader->group, $propLoaders)) {
                throw new \InvalidArgumentException(sprintf(
                    "Only one loader for serialization group '%s' can be specified",
                    $loader->group
                ));
            }

            $propLoaders[$loader->group] = $loader->loader;
        }

        return $propLoaders;
    }
}
