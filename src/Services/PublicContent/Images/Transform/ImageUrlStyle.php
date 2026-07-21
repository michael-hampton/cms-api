<?php

namespace App\Services\PublicContent\Images\Transform;

/**
 * The two canonical URL shapes the library can produce.
 *
 * Simple ("v1") writes width/quality onto the end of the filename.
 * Rich ("v2") writes a `/v2/t:..,l:..,cw:..,ch:..,q:..,w:../` parameter
 * segment ahead of the folder, and is required whenever a crop is present.
 */
enum ImageUrlStyle
{
    case Simple;
    case Rich;
}
