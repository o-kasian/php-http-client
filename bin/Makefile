#SHELL=/bin/bash -x
CURDIR = $(dir $(realpath $(lastword $(MAKEFILE_LIST))))

generate-doc:
	php $(CURDIR)/../vendor/bin/phpdoc.php -d $(CURDIR)/../src -t $(CURDIR)/../doc
