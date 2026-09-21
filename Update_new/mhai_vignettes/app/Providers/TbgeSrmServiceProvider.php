<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class TbgeSrmServiceProvider extends ServiceProvider {

	public function register(): void {
		//
	}

	public function boot(): void {
		Route::middleware('web')
			->group(base_path('routes/tbge-srm.php'));

		View::composer('tbge.templates.normal', function ($view) {
			if (!$this->isSrmExtensionPage()) {
				return;
			}

			$tbgeBase = rtrim(parse_url(url('/tbge'), PHP_URL_PATH) ?: '/tbge', '/');

			$view->with('tbgeExtensionConfig', [
				'tbgeBase' => $tbgeBase,
				'assetBase' => rtrim(parse_url(asset(''), PHP_URL_PATH) ?: '', '/'),
			]);
			$view->with('tbgeExtensionStyles', [
				asset('tbge-extensions/mosquee-compteurs-srm.css?v=7'),
			]);
			$view->with('tbgeExtensionScripts', [
				asset('tbge-extensions/mosquee-compteurs-srm.js?v=7'),
			]);
		});
	}

	private function isSrmExtensionPage(): bool {
		$path = trim(request()->path(), '/');

		return (bool) preg_match('#^tbge/patrimoine/batiment/\d+/edit$#', $path)
			|| (bool) preg_match('#^tbge/compteur/create$#', $path)
			|| (bool) preg_match('#^tbge/compteur/\d+/edit$#', $path);
	}
}
