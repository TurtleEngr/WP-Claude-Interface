# ----------
# Macros

mDistList = \
	css \
	js \
	claude.php \
	claude3.png \
	claude_set.png \
	readme.txt

# ----------
# Main Targets

usage :
	@echo Usage
	@echo build - build dist/ with dirs and files to be installed
	@echo package - create plugin install zip file
	@echo clean - rm tmp files
	@echo dist-clean - clean and remove tmp dirs

build : clean dist/claude-chat-interface
	rsync -r $(mDistList) dist/claude-chat-interface/

package : build pkg
	cd dist; zip -r ../pkg/claude-chat-interface.zip claude-chat-interface

clean :
	-find . -type f -name '*~' -exec rm {} \;
	-rm -rf dist

dist-clean : clean
	-rm -rf dist pkg

# ----------
# Single Targets

dist/claude-chat-interface :
	-mkdir -p $@

pkg :
	-mkdir -p $@
