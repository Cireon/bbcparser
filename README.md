BB-code Parser
==============
This repository contains a class that iteratively parses BB-code to HTML. The parser is heavily based on the SMF source code (see LICENSE_SMF).

How to use
----------
Create a new instance of the BBCodeParser class and call the `$parser->parse($string)` with `$string` the BB-code source. This will return the parsed HTML.

New BB-codes can be added. The structure is documented in the class itself.

This parser is currently heavily in development and is very limited in the amount of options that can be set. This parser was designed for the use on Dominating12.com, but you are free to use it anywhere where you see fit. If you make any interesting additions, it would be much appreciated if you let me know, though this is not a requirement to use this library.
