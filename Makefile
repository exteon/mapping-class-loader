.PHONY: test

test:
	export XDEBUG_MODE=debug,develop
	export XDEBUG_SESSION=1
	./vendor/bin/phpunit test
