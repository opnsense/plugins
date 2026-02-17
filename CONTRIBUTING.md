Contributing to OPNsense
========================

Thanks for considering a pull request or issue report.  Below are a
few hints and tips in order to make them as effective as possible.

Issue reports
-------------

Issue reports can be bug reports or feature requests.  Make sure to
search the issues before adding a new one.  It is often better to
join ongoing discussions on similar issues than creating a new one
as there may be workarounds or ideas available.

When creating bug reports, please make sure you provide the following:

* The current plugin version where the bug first appeared
* Add the name of the plugin to the front of the subject
* The last plugin version where the bug did not exist
* The exact URL of the GUI page involved (if any)
* A list of steps to replicate the bug
* The current OPNsense version used

All issues reported will have to be triaged and prioritised.  As we
are a small team and outside contributors we may not always have the
time to implement and help, but reporting an issue may help others
to fill in.

Plugins can be almost anything not necessary tied to our core mission,
so do not be afraid to ask.

Feature requests that relate to core components of OPNsense can be provided
using the core repository:

https://github.com/opnsense/core/issues

Stale issues are timed out after several months of inactivity.

And above all: stay kind and open.  :)

Pull requests
-------------

When creating pull request, please heed the following:

* Base your code on the latest master branch to avoid manual merges
* Code review may ensue in order to help shape your proposal
* Pull request must adhere to 2-Clause BSD licensing
* Explain the problem and your proposed solution

New plugins
-------------

The same structure as opening a pull request, but with the following additional requirements:

* Open an issue first and explain what you want to work on, wait for feedback
* The core component should be available as 2-Clause BSD licensed FreeBSD port package
* Code should be (GUI) integration around this existing port
* You will be responsible for long-term maintainance and issues
* The review process can take time, please be patient
