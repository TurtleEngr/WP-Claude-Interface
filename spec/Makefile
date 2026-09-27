# $Header: /repo/local.cvs/app/doc-WP-Claude-Interface/Makefile,v 1.6 2026/04/21 21:52:35 bruce Exp $

# ----------
# Macros

SHELL = /bin/bash

mSrc = ~/ver/github/app/WP-Claude-Interface

mFileList = \
	claude.php \
	js/claude-chat.js \
	css/claude-chat.css

# ----------
# Main targets

usage :
	less README.org

clean :
	-find . -type f -name '*~' -exec rm {} \;

.PHONY : query-prep
query-prep : tmp/query-prep.tmp

release publish :
	rsync $(mSrc)/pkg/*.zip moria:/rel/released/software/own/claude-chat-interface

# ----------
# Work targets

tmp :
	-mkdir -p $@

.PHONY : tmp/query-prep.tmp
tmp/query-prep.tmp : tmp
	'echo' -e "** Prompt:\n" >$@
	for i in $(mFileList); do \
		'echo' -e "\n** File: $$i"; \
		cat $(mSrc)/$$i; \
	done >>tmp/query-prep.tmp
