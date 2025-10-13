.PHONY: all clean install rpm doc deploy rpm-install

ifeq ($(VERSION),)
	VERSION = *DEVELOPMENT SNAPSHOT*
endif

ARCH = noarch
BINDIR ?= /usr/bin
MANDIR ?= /usr/share/man
KEYID ?= BE5096C665CA4595AF11DAB010CD9FF74E4565ED
ARCH_RPM_NAME := stdin2sql.$(ARCH).rpm

all: stdin2sql.php

doc: stdin2sql.1

rpm: $(ARCH_RPM_NAME)

rpm-install: rpm
	zypper in "./$(ARCH_RPM_NAME)"

clean:
	rm -vf -- stdin2sql stdin2sql.1 *.rpm

stdin2sql.1: README.md Makefile
	go-md2man < README.md > stdin2sql.1

install: stdin2sql.1 stdin2sql.php Makefile
	mkdir -p "$(BINDIR)" "$(MANDIR)/man1"
	install -m 755 stdin2sql.php "$(BINDIR)/"
	install -m 644 stdin2sql.1 "$(MANDIR)/man1/"

deploy: $(ARCH_RPM_NAME)
	ensure-git-clean.sh
	deploy-rpm.sh --infile=stdin2sql.src.rpm --outdir="$(RPMDIR)" --keyid="$(KEYID)" --srpm
	deploy-rpm.sh --infile="$(ARCH_RPM_NAME)" --outdir="$(RPMDIR)" --keyid="$(KEYID)"

$(ARCH_RPM_NAME) stdin2sql.src.rpm: Makefile stdin2sql.spec README.md stdin2sql.php
	easy-rpm.sh --name stdin2sql --outdir . --plain --arch "$(ARCH)" -- $^
