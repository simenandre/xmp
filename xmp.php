<?php
/*
 * XMP PHP Reader
 * https://github.com/cobraz/xmp
 *
 * Copyright 2014, Simen A. W. Olsen
 *
 * Based on work done by Jean-Sebastien Morriset - http://surniaulula.com/ 
 * Copyright 2012 - Jean-Sebastien Morisset - http://surniaulula.com/
 *
 * This script is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 3 of the License, or (at your option) any later
 * version.

 * This script is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details at
 * http://www.gnu.org/licenses/.
*/


class XMP {

        static private $max_size = 512000;
        static private $chunk_size = 65536;
        static private $start_tag = '<x:xmpmeta';
        static private $end_tag = '</x:xmpmeta>';

        static private $cache_dir = '/tmp/';
        static $use_cache = true;
        
        static function read($filename){
                $xmp_raw = self::get_xmp_raw($filename);
                return self::get_xmp_array($xmp_raw);
        }

        private static function get_xmp_raw( $filepath ) {
 
        $max_size = 512000;     // maximum size read
        $chunk_size = 65536;    // read 64k at a time
        $start_tag = '<x:xmpmeta';
        $end_tag = '</x:xmpmeta>';
        $cache_file = self::$cache_dir . md5( $filepath ) . '.xml';
        $xmp_raw = null; 
 
        if ( self::$use_cache == true && file_exists( $cache_file ) && 
                filemtime( $cache_file ) > filemtime( $filepath ) && 
                $cache_fh = fopen( $cache_file, 'rb' ) ) {
 
                $xmp_raw = fread( $cache_fh, filesize( $cache_file ) );
                fclose( $cache_fh );
 
        } elseif ( $file_fh = fopen( $filepath, 'rb' ) ) {
                                $chunk = "";
                $file_size = filesize( $filepath );
                while ( ( $file_pos = ftell( $file_fh ) ) < $file_size  && $file_pos < self::$max_size ) {
                        $chunk .= fread( $file_fh, self::$chunk_size );
                        if ( ( $end_pos = strpos( $chunk, self::$end_tag ) ) !== false ) {
                                if ( ( $start_pos = strpos( $chunk, self::$start_tag ) ) !== false ) {
 
                                        $xmp_raw = substr( $chunk, $start_pos, 
                                                $end_pos - $start_pos + strlen( self::$end_tag ) );
 
                                        if ( self::$use_cache == true && $cache_fh = fopen( $cache_file, 'wb' ) ) {
 
                                                fwrite( $cache_fh, $xmp_raw );
                                                fclose( $cache_fh );
                                        }
                                }
                                break;  // stop reading after finding the xmp data
                        }
                }
                fclose( $file_fh );
        }
        return $xmp_raw;
        }

        private static function get_xmp_array( &$xmp_raw ) {
        $xmp_arr = array();
        foreach ( array(
                'Creator Email' => '<Iptc4xmpCore:CreatorContactInfo[^>]+?CiEmailWork="([^"]*)"',
                'Owner Name'    => '<rdf:Description[^>]+?aux:OwnerName="([^"]*)"',
                'Creation Date' => '<rdf:Description[^>]+?xmp:CreateDate="([^"]*)"',
                'Modification Date'     => '<rdf:Description[^>]+?xmp:ModifyDate="([^"]*)"',
                'Label'         => '<rdf:Description[^>]+?xmp:Label="([^"]*)"',
                'Credit'        => '<rdf:Description[^>]+?photoshop:Credit="([^"]*)"',
                'Source'        => '<rdf:Description[^>]+?photoshop:Source="([^"]*)"',
                'Headline'      => '<rdf:Description[^>]+?photoshop:Headline="([^"]*)"',
                'City'          => '<rdf:Description[^>]+?photoshop:City="([^"]*)"',
                'State'         => '<rdf:Description[^>]+?photoshop:State="([^"]*)"',
                'Country'       => '<rdf:Description[^>]+?photoshop:Country="([^"]*)"',
                'Country Code'  => '<rdf:Description[^>]+?Iptc4xmpCore:CountryCode="([^"]*)"',
                'Location'      => '<rdf:Description[^>]+?Iptc4xmpCore:Location="([^"]*)"',
                'Title'         => '<dc:title>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:title>',
                'Description'   => '<dc:description>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:description>',
                'Creator'       => '<dc:creator>\s*<rdf:Seq>\s*(.*?)\s*<\/rdf:Seq>\s*<\/dc:creator>',
                'Keywords'      => '<dc:subject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/dc:subject>',
                'Hierarchical Keywords' => '<lr:hierarchicalSubject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/lr:hierarchicalSubject>'
        ) as $key => $regex ) {
 
                // get a single text string
                $xmp_arr[$key] = preg_match( "/$regex/is", $xmp_raw, $match ) ? $match[1] : '';
 
                // if string contains a list, then re-assign the variable as an array with the list elements
                $xmp_arr[$key] = preg_match_all( "/<rdf:li[^>]*>([^>]*)<\/rdf:li>/is", $xmp_arr[$key], $match ) ? $match[1] : $xmp_arr[$key];
 
                // hierarchical keywords need to be split into a third dimension
                if ( ! empty( $xmp_arr[$key] ) && $key == 'Hierarchical Keywords' ) {
                        foreach ( $xmp_arr[$key] as $li => $val ) $xmp_arr[$key][$li] = explode( '|', $val );
                        unset ( $li, $val );
                }
        }
        return $xmp_arr;
}

}