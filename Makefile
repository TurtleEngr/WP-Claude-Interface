# ----------
# Macros

mVerStr = 1.5

mDistList = \
	dist/claude-chat-interface/css \
	dist/claude-chat-interface/js \
	dist/claude-chat-interface/claude.php \
	dist/claude-chat-interface/claude3.png \
	dist/claude-chat-interface/claude_set.png \
	dist/claude-chat-interface/readme.txt

# ----------
# Main Targets

usage :
	@echo Usage
	@echo build - build dist/ with dirs and files to be installed
	@echo package - create plugin install zip file
	@echo clean - rm tmp files
	@echo dist-clean - clean and remove tmp dirs

build : clean dist/claude-chat-interface $(mDistList)
	sed -i 's/version-[0-9]\.[0-9]*-orange/version-$(mVerStr)-orange/' README.md

package : build pkg
	cd dist; zip -r ../pkg/claude-chat-interface-$(mVerStr).zip claude-chat-interface

clean :
	-find . -type f -name '*~' -exec rm {} \;
	-rm -rf dist

dist-clean : clean
	-rm -rf dist pkg tmp

# ----------
# Single Targets

dist/claude-chat-interface pkg :
	-mkdir -p $@

dist/claude-chat-interface/css : css
	rsync -r $? dist/claude-chat-interface/

dist/claude-chat-interface/js : js
	rsync -r $? dist/claude-chat-interface/

dist/claude-chat-interface/claude.php : claude.php
	sed 's/mVerStr/$(mVerStr)/' <$? >$@

dist/claude-chat-interface/readme.txt : readme.txt
	sed 's/mVerStr/$(mVerStr)/' <$? >$@

dist/claude-chat-interface/claude3.png : claude3.png
	cp $? $@

dist/claude-chat-interface/claude_set.png : claude_set.png
	cp $? $@
