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

clean :
	-find . -type f -name '*~' -exec rm {} \;

dist-clean : clean
	-rm -rf dist pkg

build : dist/claude-chat-interface
	rsync -r $(mDistList) dist/claude-chat-interface/

package : build pkg
	cd dist; zip -r ../pkg/claude-chat-interface.zip claude-chat-interface

# ----------
# Single Targets

dist/claude-chat-interface :
	-mkdir -p $@

pkg :
	-mkdir -p $@
