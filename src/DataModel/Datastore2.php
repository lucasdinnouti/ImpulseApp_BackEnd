<?php

/*
 * Copyright 2015 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Samples\publicshelf\DataModel;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;

/**
 * Class Datastore implements the DataModel with a Google Data Store.
 */
class Datastore implements DataModelInterface
{
    private $datasetId;
    private $datastore;
    protected $columns = [
        'título'        => 'string',
        'data'          => 'timestamp',
        'texto'         => 'string',
        'imagem'        => 'integer',
        'autor'         => 'string',
        'publicado'     => 'integer'
    ];

    public function __construct($projectId)
    {
        $this->datasetId = $projectId;
        $this->datastore = new DatastoreClient([
            'projectId' => $projectId,
        ]);
    }

    public function listpublics($limit = 10, $cursor = null)
    {
        $query = $this->datastore->query()
            ->kind('Publicacao')
            ->order('data')
            ->limit($limit)
            ->start($cursor);

        $results = $this->datastore->runQuery($query);

        $publics = [];
        $nextPageCursor = null;
        foreach ($results as $entity) {
            $publicacao = $entity->get();
            $publicacao['id'] = $entity->key()->pathEndIdentifier();
            $publics[] = $publicacao;
            $nextPageCursor = $entity->cursor();
        }

        return [
            'publics' => $publics,
            'cursor' => $nextPageCursor,
        ];
    }

    public function create($publicacao, $key = null)
    {
        $this->verifypublicacao($publicacao);

        $key = $this->datastore->key('publicacao');
        $entity = $this->datastore->entity($key, $publicacao);

        $this->datastore->insert($entity);

        // return the ID of the created datastore entity
        return $entity->key()->pathEndIdentifier();
    }

    public function read($id)
    {
        $key = $this->datastore->key('publicacao', $id);
        $entity = $this->datastore->lookup($key);

        if ($entity) {
            $publicacao = $entity->get();
            $publicacao['id'] = $id;
            return $publicacao;
        }

        return false;
    }

    public function update($publicacao)
    {
        $this->verifypublicacao($publicacao);

        if (!isset($publicacao['id'])) {
            throw new \InvalidArgumentException('publication must have an "id" attribute');
        }

        $transaction = $this->datastore->transaction();
        $key = $this->datastore->key('publicacao', $publicacao['id']);
        $task = $transaction->lookup($key);
        unset($publicacao['id']);
        $entity = $this->datastore->entity($key, $publicacao);
        $transaction->upsert($entity);
        $transaction->commit();

        // return the number of updated rows
        return 1;
    }

    public function delete($id)
    {
        $key = $this->datastore->key('publicacao', $id);
        return $this->datastore->delete($key);
    }

    private function verifypublicacao($publicacao)
    {
        if ($invalid = array_diff_key($publicacao, $this->columns)) {
            throw new \InvalidArgumentException(sprintf(
                'unsupported publication properties: "%s"',
                implode(', ', $invalid)
            ));
        }
    }
}
