<?php

namespace App\Core\Interfaces;


interface CollectionQueryFilterInterface
{

	public function apply(CollectionInterface $collection) : CollectionQueryFilterInterface;

}