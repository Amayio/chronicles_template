<?php
/**
 * Custom functions
 *
 * @package   MyAAC
 * @author    Slawkens <slawkens@gmail.com>, Lee
 * @copyright 2020 MyAAC
 * @link      https://my-aac.org
 */

// Insert your custom functions here.

function chronicles_footer(): string {
    global $twig, $status;
    
    return $twig->render('aachronicles.footer.html.twig',
		[
			'status' => $status,
		]
	);
}

function chronicles_header(): string {
    global $twig, $status;
    
    return $twig->render('aachronicles.header.html.twig',
		[
			'status' => $status,
		]
	);
}