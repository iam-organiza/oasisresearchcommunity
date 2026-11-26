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
<ul>
    <li><strong>Your account:</strong> your name, email address and password. We store only a scrambled (hashed) version
    of your password, never the password itself.</li>
    <li><strong>A membership application:</strong> your title, phone number, the state and city you live in, the grade
    you apply for, your qualifications, current role and institution, years of research experience, your publications,
    the research areas you have experience in, how you qualify (including any reason and links for an exam exemption),
    your induction event, and the documents you upload, such as your CV and certificates.</li>
    <li><strong>A referee, if you name one:</strong> their name, email address, how they know you, and their answer to
    our request.</li>
    <li><strong>Membership:</strong> once approved, your membership number, grade, chapter, the date you joined and
    whether your fees have been received or waived.</li>
    <li><strong>Comments</strong> on the Knowledge Base: the name and email address you give, and your comment.</li>
    <li><strong>Security records:</strong> to protect accounts, we briefly record failed log-in attempts so an account can
    be locked for 15 minutes after five wrong passwords.</li>
</ul>
<p>We do not ask for your National Identification Number, Bank Verification Number or bank card details.</p>

<h3>3. Why we use it</h3>
<ul>
    <li>To run your account and let you log in, because you asked us to (our agreement with you).</li>
    <li>To assess your membership application, decide your grade, and keep the member register, which is what you apply
    for and what a professional body must do.</li>
    <li>To email you about your application and membership, such as receipts, decisions and requests for more
    information.</li>
    <li>To ask your referee whether they can vouch for you. When you name a referee, please let them know.</li>
    <li>To list you in the member directory, but only if you agree to it on your application. You can change your mind
    at any time.</li>
    <li>To keep the website secure and prevent misuse.</li>
</ul>
<p>We do not sell your personal data, and we do not use it for advertising.</p>

<h3>4. Who can see it</h3>
<ul>
    <li><strong>OASIS staff with a role on the website:</strong> the Registrar and administrators see membership
    applications and the member register; editors see Knowledge Base comments.</li>
    <li><strong>Your referee</strong> sees your name and that you named them. They do not see your application.</li>
    <li><strong>Our hosting provider</strong> stores the website and its data, and sends our emails. Their servers may be
    outside Nigeria; we use them only to run this website.</li>
    <li><strong>Other members and visitors</strong> see your name, grade and chapter only if you agree to be listed in the
    member directory.</li>
</ul>

<h3>5. How long we keep it</h3>
<ul>
    <li><strong>Documents of a declined application</strong> are deleted automatically 6 months after the decision, or
    after 2 years if you asked us to keep them in case you reapply.</li>
    <li><strong>The rest of an application</strong> stays with your account as the record of the decision.</li>
    <li><strong>Membership records</strong> are kept for as long as you are a member, and afterwards as part of the register
    of members we must keep as a professional body.</li>
    <li><strong>Your account</strong> is kept until you delete it from your settings, or ask us to delete it.</li>
    <li><strong>Comments</strong> stay published until they are removed by you or by us.</li>
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
<p>Email <a href="mailto:{{ config('oasis.support_email') }}">{{ config('oasis.support_email') }}</a> and we will reply
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
