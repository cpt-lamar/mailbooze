# Mailbooze - self-hosted email for addicts

This is a humble attempt at solving my personal self-hosting ambitions, with a faily complete e-mail solution that works well on cheap Single Board Computers like the raspberry Pi, odroid, olinuxino and the likes.

In a nutshell, you will find a set of Docker container definitions for :
* Haraka for inbound SMTP
* Dovecot for e-mail storage and IMAP access
* Rainloop webmail (using Nginx/php/postgresql)
* fetchmail to grab e-mail from your other accounts

In order to get this combo up-and-running, you will need
* an ARM SBC (raspberry Pi, odroid, etc), with a decent linux distribution and docker tools.
* a public domain name, if you wish to receive e-mails from the outer world - your SBC hostname will suffice for some tests
* to follow some instructions that I need to write down

At the moment, this combo is working fine on my home server - yet it is of course highly experimental...
