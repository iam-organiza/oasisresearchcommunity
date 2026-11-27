{{--
    Privacy Policy. Drafted on 27 November 2026 from what the website actually collects and does;
    OASIS's legal adviser should review it before it is published on the live site.
--}}
<p>This policy explains what personal data OASIS Research Community collects through
<a href="{{ url('/') }}">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>, why, who can see it, how long we keep it, and your rights
under the Nigeria Data Protection Act 2023.</p>

<h3>1. Who we are</h3>
<p>OASIS Research Community (the Organization of Academic Scholars, Institutions and Societies, “OASIS”, “we”, “us”)
is a professional body for researchers, registered with the Corporate Affairs Commission as a company limited by
guarantee. We decide how your personal data is used on this website.</p>
<p>National Secretariat: {{ implode(' ', config('oasis.address')) }}.<br>
Email: <a href="mailto:{{ config('oasis.support_email') }}">{{ config('oasis.support_email') }}</a>.</p>

<h3>2. What we collect</h3>
<ul class="policy-terms">
    <li><strong>Your account:</strong> <span>your name, email address and password. We store only a scrambled (hashed) version
    of your password, never the password itself.</span></li>
    <li><strong>A membership application:</strong> <span>your title, phone number, the state and city you live in, the grade
    you apply for, your qualifications, whether you are studying, the kind of work you do, your current role and
    institution, years of research experience, your publications, the research areas you have experience in, anything
    else you choose to tell us, the grade we offer you and why, how you qualify (including any reason and links for an exam exemption),
    your induction event, and the documents you upload, such as your CV and certificates.</span></li>
    <li><strong>A delivery address, only if you ask us to post your induction pack:</strong> <span>the street address, city,
    state and a phone number for the courier.</span></li>
    <li><strong>Membership:</strong> <span>once approved, your membership number, grade, chapter, the date you joined and
    whether your fees have been received or waived.</span></li>
    <li><strong>Comments</strong> <span>on the Knowledge Base: the name and email address you give, and your comment.</span></li>
    <li><strong>Security records:</strong> <span>to protect accounts, we briefly record failed log-in attempts so an account can
    be locked for 15 minutes after five wrong passwords.</span></li>
</ul>
<p class="policy-promise">We do not ask for your National Identification Number, Bank Verification Number or bank card details.</p>

<h3>3. Why we use it</h3>
<ul>
    <li>To run your account and let you log in, because you asked us to (our agreement with you).</li>
    <li>To assess your membership application, decide your grade, and keep the member register, which is what you apply
    for and what a professional body must do.</li>
    <li>To offer you a grade as soon as you apply. The website compares your answers (qualification, years of research,
    publications, the kind of work you do and the areas you have experience in) with each grade's published criteria.
    The Registrar, a person, can check and change any offer, reads anything else you tell us, and decides every appeal:
    if you disagree with your offer you can appeal from your membership page and a person will look at it.</li>
    <li>To email you about your application and membership, such as receipts, decisions, requests for more
    information and reminders when your yearly subscription is due.</li>
    <li>To send members a short update once a month: where your membership stands and what is new in the Knowledge
    Base. You can stop it from the link at the bottom of any update; receipts and subscription reminders still come.</li>
    <li>To post your induction pack (certificate, award and gown) if you cannot attend the ceremony and ask us to.</li>
    <li>To take payments online through Paystack, and keep a record of every fee, payment, waiver and receipt on your
    account.</li>
    <li>To list you in the member directory, but only if you agree to it on your application. You can change your mind
    at any time.</li>
    <li>To keep the website secure and prevent misuse.</li>
</ul>
<p class="policy-promise">We do not sell your personal data, and we do not use it for advertising.</p>

<h3>4. Who can see it</h3>
<ul class="policy-terms">
    <li><strong>OASIS staff with a role on the website:</strong> <span>the Registrar and administrators see membership
    applications and the member register; editors see Knowledge Base comments.</span></li>
    <li><strong>Our hosting provider</strong> <span>stores the website and its data, and sends our emails. Their servers may be
    outside Nigeria; we use them only to run this website.</span></li>
    <li><strong>Paystack, when you pay online:</strong> <span> we send them your email address, the amount and what it is for.
    You give your card or bank details to Paystack on their own page; we never see or keep them. Paystack's own
    privacy policy covers what they do with them.</span></li>
    <li><strong>Other members and visitors</strong> <span>see your name, grade and chapter only if you agree to be listed in the
    member directory.</span></li>
</ul>

<h3>5. How long we keep it</h3>
<ul class="policy-terms">
    <li><strong>Documents of a declined application</strong> <span>are deleted automatically 6 months after the decision, or
    after 2 years if you asked us to keep them in case you reapply.</span></li>
    <li><strong>The rest of an application</strong> <span>stays with your account as the record of the decision.</span></li>
    <li><strong>A delivery address</strong> <span>is deleted as soon as your induction pack is delivered.</span></li>
    <li><strong>Membership records</strong> <span>are kept for as long as you are a member, and afterwards as part of the register
    of members we must keep as a professional body.</span></li>
    <li><strong>Your account</strong> <span>is kept until you delete it from your settings, or ask us to delete it.</span></li>
    <li><strong>Comments</strong> <span>stay published until they are removed by you or by us.</span></li>
</ul>

<h3>6. Your rights</h3>
<p>Under the Nigeria Data Protection Act 2023 you can ask us to:</p>
<ul>
    <li>tell you what personal data we hold about you and give you a copy;</li>
    <li>correct anything that is wrong or incomplete;</li>
    <li>delete your personal data, where we no longer need it or you withdraw your consent;</li>
    <li>stop or limit using it, or send it to you or another organisation in a common format;</li>
    <li>withdraw a consent you gave, such as the member directory listing or keeping your documents for 2 years.</li>
</ul>
<p class="policy-promise">Email <a href="mailto:{{ config('oasis.support_email') }}">{{ config('oasis.support_email') }}</a> and we will reply
within 30 days. You can also complain to the Nigeria Data Protection Commission at
<a href="https://ndpc.gov.ng">ndpc.gov.ng</a>.</p>

<h3>7. Keeping it safe</h3>
<ul>
    <li>The website uses an encrypted connection (HTTPS).</li>
    <li>Uploaded documents are stored privately: only you and the staff who review applications can open them.</li>
    <li>Staff see only the parts of the website their role needs.</li>
</ul>

<h3>8. Cookies</h3>
<p>We use only the cookies the website needs to work: one that keeps you logged in, one that protects forms against
misuse, and two that remember your light or dark appearance and whether the side menu is open. We do not use
advertising or tracking cookies. The map in our Contact section is provided by Google, which may set its own cookies
when the map loads.</p>

<h3>9. Changes to this policy</h3>
<p>If we change how we use personal data, we will update this page and the date at the top. For important changes we
will also email members.</p>

<h3>10. Contact</h3>
<p>Questions about this policy or your personal data: <a href="mailto:{{ config('oasis.support_email') }}">{{ config('oasis.support_email') }}</a>,
or write to the National Secretariat at the address above.</p>
