<?php

namespace App\Models\Tbge\Concerns;

use DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasSrmReference {

	public function scopeMatchingReference($query, $reference) {
		$ref = Str::lower(trim((string) $reference));

		return $query->where(function ($q) use ($ref) {
			$q->where(DB::raw('LOWER(reference)'), '=', $ref);
			if (Schema::connection('tbge')->hasColumn('compteur', 'reference_ancienne')) {
				$q->orWhere(DB::raw('LOWER(COALESCE(reference_ancienne, \'\'))'), '=', $ref);
			}
		});
	}
}
