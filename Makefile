# ----------
# Macros

mDistList = \
	css \
	js \
	claude.php \
	readme.txt \
	claude3.png \
	claude_set.png

# ----------
# Main Targest

clean :
	-find . -type f -name '*~' -exec rm {} \;

dist-clean : clean
	-rm -rf dist
	-rm README.html 

build : dist/claude-chat-interface
	rsync -r $(mDistList) dist/claude-chat-interface/

# ----------
# Single Targets

dist/claude-chat-interface :
	-mkdir -p $@
