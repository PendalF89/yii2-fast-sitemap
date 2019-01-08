<?php

namespace pendalf89\fast_sitemap;

use Yii;
use yii\base\BaseObject;

class Sitemap extends BaseObject
{
	/**
	 * @var int Max urls in each sitemap file
	 */
	public $maxUrlsPerFile = 50000;

	/**
	 * @var bool Whether to compress files with gzip
	 */
	public $compressWithGzip = true;

	/**
	 * @var string Index sitemap name
	 */
	public $indexSitemapName = 'sitemap';

	/**
	 * @var string Path to sitemaps
	 */
	public $path = '@frontend/web';

	/**
	 * @var bool Whether to ping search engines if the new index site map is different from the previous one
	 */
	public $pingSearchEngines = true;

	/**
	 * @var string Base domain for urls. For example: https://site.com (without "/" on the end of line
	 */
	public $domain;

	/**
	 * @var array Index sitemap data
	 */
	protected $indexSitemap = [];

	/**
	 * Create sitemap file
	 *
	 * @param SitemapInterface $sitemap
	 */
	public function create(SitemapInterface $sitemap)
	{
		$offset  = 0;
		$counter = 0;
		do {
			if ($items = $sitemap->getItems($offset, $this->maxUrlsPerFile)) {
				$file                 = $this->write($this->toString($items, $sitemap), $sitemap, $counter);
				$this->indexSitemap[] = [
					'sitemap' => $sitemap,
					'date'    => $sitemap->getLastmod($items[0]),
					'file'    => $file,
				];
				$offset               += $this->maxUrlsPerFile;
				$counter++;
			}
		} while ($items);
	}

	/**
	 * Create index sitemap file and ping Search Engines if is necessary
	 */
	public function createIndex()
	{
		$oldHash = '';
		if ($this->pingSearchEngines) {
			$oldHash = $this->getHashFromIndexSitemap();
		}

		usort($this->indexSitemap, function ($a, $b) {
			$a = strtotime($a['date']);
			$b = strtotime($b['date']);
			if ($a === $b) {
				return 0;
			}

			return ($a > $b) ? -1 : 1;
		});
		$priorityStep = 1 / count($this->indexSitemap);
		$priority     = 1;
		$str          = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
		                . ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9'
		                . ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"'
		                . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ($this->indexSitemap as $item) {
			$str .= '<url><loc>' . $this->domain . '/' . basename($item['file']) . '</loc>';
			if ($item['date']) {
				$str .= '<lastmod>' . date('Y-m-d', strtotime($item['date'])) . '</lastmod>';
			}
			$str      .= '<priority>' . (rtrim(sprintf('%.5f', $priority), '.0')) . '</priority>';
			$str      .= '</url>';
			$priority -= $priorityStep;
		}
		$str  .= '</urlset>';
		$file = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->indexSitemapName . '.xml';

		if ($this->compressWithGzip) {
			$this->writeGZipFile($file, $str);
		} else {
			file_put_contents($file, $str);
		}

		if ($this->pingSearchEngines) {
			$newHash = $this->getHashFromIndexSitemap();
			if ($oldHash !== $newHash) {
				$this->ping();
			}
		}
	}

	/**
	 * Ping Search Engines
	 */
	protected function ping()
	{
		$sitemapUrl      = $this->domain . '/' . $this->indexSitemapName . '.xml' . ($this->compressWithGzip ? '.gz' : '');
		$searchEngines[] = 'https://www.google.com/ping?' . http_build_query(['sitemap' => $sitemapUrl]);
		$searchEngines[] = 'https://www.bing.com/webmaster/ping.aspx?' . http_build_query(['siteMap' => $sitemapUrl]);
		foreach ($searchEngines as $url) {
			file_get_contents($url);
		}
	}

	/**
	 * Read hash from index sitemap file
	 *
	 * @return string
	 */
	protected function getHashFromIndexSitemap()
	{
		$file = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $this->indexSitemapName . '.xml';
		if (!file_exists($file)) {
			$file .= '.gz';
			if (!file_exists($file)) {
				return '';
			}
		}

		return md5(file_get_contents($file));
	}

	/**
	 * Create sitemap content from items
	 *
	 * @param $items
	 * @param SitemapInterface $sitemap
	 *
	 * @return string
	 */
	protected function toString($items, SitemapInterface $sitemap)
	{
		$priorityStep = 1 / count($items);
		$priority     = 1;
		$str          = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
		                . ' xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9'
		                . ' http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"'
		                . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ($items as $item) {
			$str .= '<url><loc>' . $this->domain . $sitemap->getUrl($item) . '</loc>';
			if ($lastmod = $sitemap->getLastmod($item)) {
				$str .= '<lastmod>' . $lastmod . '</lastmod>';
			}
			$str      .= '<priority>' . (rtrim(sprintf('%.5f', $priority), '.0')) . '</priority>';
			$str      .= '</url>';
			$priority -= $priorityStep;
		}
		$str .= '</urlset>';

		return $str;
	}

	/**
	 * Write sitemap file
	 *
	 * @param $str
	 * @param SitemapInterface $sitemap
	 * @param $counter
	 *
	 * @return string
	 */
	protected function write($str, SitemapInterface $sitemap, $counter)
	{
		$prefix = $counter ? "-$counter" : '';
		$file   = Yii::getAlias($this->path) . DIRECTORY_SEPARATOR . $sitemap->getName() . $prefix . '.xml';

		if ($this->compressWithGzip) {
			$this->writeGZipFile($file, $str);
		} else {
			file_put_contents($file, $str);
		}

		return $file;
	}

	/**
	 * Save GZipped file.
	 *
	 * @param string $content
	 * @param string $filename
	 *
	 * @return bool
	 */
	protected function writeGZipFile($filename, $content)
	{
		$filename .= '.gz';
		$file     = gzopen($filename, 'w');
		gzwrite($file, $content);

		return gzclose($file);
	}
}