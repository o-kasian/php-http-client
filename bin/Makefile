#SHELL=/bin/bash -x
CURDIR = $(dir $(realpath $(lastword $(MAKEFILE_LIST))))

generate-doc:
	cd $(CURDIR); cd ../; \
	rm -rf doc; \
	php vendor/bin/phpdoc.php --visibility=public --directory=src --target=doc --template=responsive --title="php HttpClient"