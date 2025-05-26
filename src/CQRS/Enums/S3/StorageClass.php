<?php
declare(strict_types=1);

namespace App\CQRS\Enums\S3;

enum StorageClass : string
{

	case STANDARD = 'STANDARD';
	case ONE_ZONE_IA = 'ONEZONE_IA';
	case GLACIER = 'GLACIER';

}
