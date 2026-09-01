# ----------
# Macros

SHELL := /bin/bash

mProj = WP-Claude-Interface
mProduct = dist/claude-chat-interface-VERSION.zip

mDistList = \
	dist/claude-chat-interface \
	dist/claude-chat-interface/css \
	dist/claude-chat-interface/js \
	dist/claude-chat-interface/claude.php \
	dist/claude-chat-interface/claude3.png \
	dist/claude-chat-interface/claude_set.png \
	dist/claude-chat-interface/readme.txt \
	dist/claude-chat-interface/LICENSE

mReleaseDir = moria.whyayh.com:/rel/released/software/own/claude-chat-interface

# ----------
# Main Targets

usage :
	@echo "Usage:"
	@echo "build - build dist/ with dirs and files to be installed"
	@echo "package - create plugin install zip file"
	@echo "release - copy zip files to release area"
	@echo "clean - rm tmp files"
	@echo "dist-clean - clean and remove tmp dirs"

update :
	git co develop
	git pull --tags origin develop

build : clean dist/claude-chat-interface $(mDistList)
	sed -E -i "s;version-[0-9]+(\.[0-9]+){1,3}-orange;version-$$(cat VERSION)-orange;" README.md

package : build
	cd dist; zip -r claude-chat-interface-$$(cat ../VERSION)).zip claude-chat-interface

tag :
	git tag -f ver-$$(cat VERSION)

release : tag
	'rsync' -aP readme.txt dist/claude-chat-interface-$$(cat VERSION).zip $(mReleaseDir)

clean :
	-find . -type f -name '*~' -exec rm {} \;
	-rm -rf dist

dist-clean : clean
	-rm -rf dist tmp

# ----------
# Work Targets

$(mProduct) : dist/claude-chat-interface $(mBuildList)
	-rm $@
	cd dist; zip -r claude-chat-interface-$$(cat VERSION).zip claude-chat-interface

# ----------
# Single Targets

VERSION :
	echo '0.0.0' >$@

incPatch : VERSION
	incver.sh -p

incMinor : VERSION
	incver.sh -m

incMajor : VERSION
	incver.sh -M

dist/claude-chat-interface :
	-mkdir -p $@

dist/claude-chat-interface/css : css
	rsync -r $? dist/claude-chat-interface/

dist/claude-chat-interface/js : js
	rsync -r $? dist/claude-chat-interface/

dist/claude-chat-interface/claude.php : claude.php
	sed "s/VERSION/$$(cat VERSION)/" <$? >$@

dist/claude-chat-interface/readme.txt : readme.txt
	sed "s/VERSION/$$(cat VERSION)/" <$? >$@

dist/claude-chat-interface/claude3.png : claude3.png
	cp $? $@

dist/claude-chat-interface/claude_set.png : claude_set.png
	cp $? $@
