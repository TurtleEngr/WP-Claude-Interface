# ----------
# Macros

mDistList = \
	css \
	js \
	claude.php \
	claude3.png \
	claude_set.png

# ----------
# Main Targets

clean :
	-find . -type f -name '*~' -exec rm {} \;

dist-clean : clean
	-rm -rf dist

build : dist/claude-chat-interface
	rsync -r $(mDistList) dist/claude-chat-interface/
	cp README.md dist/claude-chat-interface/readme.txt

# ----------
# Single Targets

dist/claude-chat-interface :
	-mkdir -p $@
