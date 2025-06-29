<?php
declare(strict_types=1);

namespace App\Models\Auth;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id_user_type')]
class UserType extends \Lsr\Core\Auth\Models\UserType
{

	#[ManyToMany(through: 'user_type_hierarchy', foreignKey: 'id_managed_type', localKey: 'id_user_type', class: UserType::class)]
	public ModelCollection $managedTypes;

	public function managesType(UserType $type) : bool {
		if ($this->superAdmin) {
			return true;
		}
		return $this->managedTypes->first(fn (UserType $t) => $t->id === $type->id) !== null;
	}

}