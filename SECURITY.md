# Security policy

## Reporting a vulnerability

Please **don't open a public issue** for security problems.

Report it privately through GitHub instead: on this repository, open the **Security** tab and choose **Report a vulnerability**. Only the maintainer can see the report.

Useful things to include:

- what an attacker could do, and under which conditions (signed in? another account?)
- steps or a request that reproduces it
- the commit or date of the version you tested

You'll get an answer as soon as possible. Once a fix is released, you'll be credited in the commit message unless you'd rather not be.

## Supported versions

Only the latest commit on `main` is supported. There are no separate release branches.

## Scope

The app is designed to run on your own machine (see *Before exposing it to a network* in the [README](README.md)). Findings that only apply to a public deployment are still welcome: they help whoever hosts an instance.

The security measures already in place are listed under **Engineering highlights → Security** in the README.
