<?php
/*
 * This file is part of the reva2/jsonapi.
 *
 * (c) Sergey Revenko <dedsemen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Reva2\JsonApi\Annotations;

/**
 * JSON API Resource
 *
 * @package Reva2\JsonApi\Annotations
 * @author Sergey Revenko <dedsemen@gmail.com>
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiResource extends ApiObject
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $loader;
}
