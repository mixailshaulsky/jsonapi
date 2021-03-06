<?php
/*
 * This file is part of the reva2/jsonapi.
 *
 * (c) Sergey Revenko <dedsemen@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Reva2\JsonApi\Tests\Fixtures\Query;

use Reva2\JsonApi\Http\Query\QueryParameters;

/**
 * Query parameters for pets API
 *
 * @package Reva2\JsonApi\Tests\Fixtures\Query
 * @author Sergey Revenko <dedsemen@gmail.com>
 */
class PetQuery extends QueryParameters
{
    /**
     * @inheritdoc
     */
    protected function getAllowedIncludePaths()
    {
        return ['store'];
    }

    /**
     * @inheritdoc
     */
    protected function getAllowedFields($resource)
    {
        switch ($resource) {
            case 'pets':
                return ['name', 'family', 'store'];

            case 'stores':
                return ['name'];

            default:
                return parent::getAllowedFields($resource);
        }
    }
}