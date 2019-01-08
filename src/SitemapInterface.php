<?php

namespace pendalf89\fast_sitemap;

interface SitemapInterface
{
	/**
	 * Sitemap name
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Method for getting items
	 *
	 * @param int $offset
	 * @param int $limit
	 *
	 * @return array
	 */
	public function getItems($offset, $limit);

	/**
	 * Url
	 *
	 * @param mixed $item
	 *
	 * @return string
	 */
	public function getUrl($item);

	/**
	 * Last modified for url
	 *
	 * @param mixed $item
	 *
	 * @return string
	 */
	public function getLastmod($item);
}