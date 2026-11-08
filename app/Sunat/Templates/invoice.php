<?php

/**
 * Plantilla de factura UBL 2.1.
 * Separa la estructura XML del servicio PHP que prepara los datos del emisor
 * y del comprobante; el firmador completa después el espacio ExtensionContent.
 */
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string => number_format((float) $value, 2, '.', '');
$invoiceId = $document->series.'-'.str_pad((string) $document->number, 8, '0', STR_PAD_LEFT);
$customerNumber = (string) ($document->customer_document_number ?: '0');
$customerDocumentType = (string) ($document->customer_document_type ?: '0');
$currency = (string) $document->currency;
$signatureId = 'SIGN-'.$ruc;

$renderParty = static function (string $number, string $scheme, string $name, string $address, ?string $establishmentCode = null) use ($escape): string {
    $addressXml = '';
    if ($address !== '' || $establishmentCode !== null) {
        $addressTypeCode = $establishmentCode !== null ? '<cbc:AddressTypeCode>'.$escape($establishmentCode).'</cbc:AddressTypeCode>' : '';
        $addressLine = $address !== '' ? '<cac:AddressLine><cbc:Line>'.$escape($address).'</cbc:Line></cac:AddressLine>' : '';
        $addressXml = '<cac:RegistrationAddress>'.$addressTypeCode.$addressLine.'<cac:Country><cbc:IdentificationCode>PE</cbc:IdentificationCode></cac:Country></cac:RegistrationAddress>';
    }

    return '<cac:Party><cac:PartyIdentification><cbc:ID schemeID="'.$escape($scheme).'">'.$escape($number).'</cbc:ID></cac:PartyIdentification><cac:PartyLegalEntity><cbc:RegistrationName>'.$escape($name).'</cbc:RegistrationName>'.$addressXml.'</cac:PartyLegalEntity></cac:Party>';
};

$renderTaxScheme = static fn (): string => '<cac:TaxScheme><cbc:ID>1000</cbc:ID><cbc:Name>IGV</cbc:Name><cbc:TaxTypeCode>VAT</cbc:TaxTypeCode></cac:TaxScheme>';
?><?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
    <ext:UBLExtensions>
        <ext:UBLExtension><ext:ExtensionContent/></ext:UBLExtension>
    </ext:UBLExtensions>
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>2.0</cbc:CustomizationID>
    <cbc:ID><?= $escape($invoiceId) ?></cbc:ID>
    <cbc:IssueDate><?= $escape($document->issue_date->format('Y-m-d')) ?></cbc:IssueDate>
    <cbc:IssueTime><?= $escape(now()->format('H:i:s')) ?></cbc:IssueTime>
    <cbc:InvoiceTypeCode listID="0101" listAgencyName="PE:SUNAT" listName="Tipo de Documento" listURI="urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo01"><?= $escape($document->document_type) ?></cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode listID="ISO 4217 Alpha" listName="Currency" listAgencyName="United Nations Economic Commission for Europe"><?= $escape($currency) ?></cbc:DocumentCurrencyCode>
    <cac:Signature>
        <cbc:ID><?= $escape($signatureId) ?></cbc:ID>
        <cac:SignatoryParty>
            <cac:PartyIdentification><cbc:ID><?= $escape($ruc) ?></cbc:ID></cac:PartyIdentification>
            <cac:PartyName><cbc:Name><?= $escape($companyName) ?></cbc:Name></cac:PartyName>
        </cac:SignatoryParty>
        <cac:DigitalSignatureAttachment><cac:ExternalReference><cbc:URI>#<?= $escape($signatureId) ?></cbc:URI></cac:ExternalReference></cac:DigitalSignatureAttachment>
    </cac:Signature>
    <cac:AccountingSupplierParty>
        <cbc:CustomerAssignedAccountID><?= $escape($ruc) ?></cbc:CustomerAssignedAccountID>
        <cbc:AdditionalAccountID>6</cbc:AdditionalAccountID>
        <?= $renderParty($ruc, '6', $companyName, (string) $settings['address'], (string) $settings['establishment_code']) ?>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty><?= $renderParty($customerNumber, $customerDocumentType, $document->customer_name, (string) ($document->customer_address ?? '')) ?></cac:AccountingCustomerParty>
<?php if (($document->payment_condition ?? 'cash') === 'credit'): ?>
    <cac:PaymentTerms>
        <cbc:ID>FormaPago</cbc:ID>
        <cbc:PaymentMeansID>Credito</cbc:PaymentMeansID>
        <cbc:Amount currencyID="<?= $escape($currency) ?>"><?= $money($document->total) ?></cbc:Amount>
    </cac:PaymentTerms>
    <cac:PaymentTerms>
        <cbc:ID>FormaPago</cbc:ID>
        <cbc:PaymentMeansID>Cuota001</cbc:PaymentMeansID>
        <cbc:Amount currencyID="<?= $escape($currency) ?>"><?= $money($document->total) ?></cbc:Amount>
        <cbc:PaymentDueDate><?= $escape($document->payment_due_date?->format('Y-m-d') ?? '') ?></cbc:PaymentDueDate>
    </cac:PaymentTerms>
<?php else: ?>
    <cac:PaymentTerms>
        <cbc:ID>FormaPago</cbc:ID>
        <cbc:PaymentMeansID>Contado</cbc:PaymentMeansID>
    </cac:PaymentTerms>
<?php endif; ?>
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->tax_total) ?></cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->subtotal) ?></cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->tax_total) ?></cbc:TaxAmount>
            <cac:TaxCategory><cbc:ID><?= (float) $document->tax_total > 0 ? 'S' : 'E' ?></cbc:ID><?= $renderTaxScheme() ?></cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->subtotal) ?></cbc:LineExtensionAmount>
        <cbc:TaxInclusiveAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->total) ?></cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="<?= $escape($currency) ?>"><?= $money($document->total) ?></cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
<?php foreach ($document->lines as $index => $line): ?>
    <cac:InvoiceLine>
        <cbc:ID><?= $index + 1 ?></cbc:ID>
        <cbc:InvoicedQuantity unitCode="<?= $escape($line['unit_code']) ?>"><?= $escape($line['quantity']) ?></cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="<?= $escape($currency) ?>"><?= $money($line['subtotal']) ?></cbc:LineExtensionAmount>
        <cac:PricingReference>
            <cac:AlternativeConditionPrice>
                <cbc:PriceAmount currencyID="<?= $escape($currency) ?>"><?= $money((float) $line['unit_price'] * (1 + (float) $line['tax_rate'] / 100)) ?></cbc:PriceAmount>
                <cbc:PriceTypeCode>01</cbc:PriceTypeCode>
            </cac:AlternativeConditionPrice>
        </cac:PricingReference>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="<?= $escape($currency) ?>"><?= $money($line['igv']) ?></cbc:TaxAmount>
            <cac:TaxSubtotal>
                <cbc:TaxableAmount currencyID="<?= $escape($currency) ?>"><?= $money($line['subtotal']) ?></cbc:TaxableAmount>
                <cbc:TaxAmount currencyID="<?= $escape($currency) ?>"><?= $money($line['igv']) ?></cbc:TaxAmount>
                <cac:TaxCategory>
                    <cbc:ID><?= (float) $line['igv'] > 0 ? 'S' : 'E' ?></cbc:ID>
                    <cbc:Percent><?= $escape($line['tax_rate']) ?></cbc:Percent>
                    <cbc:TaxExemptionReasonCode><?= (float) $line['igv'] > 0 ? '10' : '20' ?></cbc:TaxExemptionReasonCode>
                    <?= $renderTaxScheme() ?>
                </cac:TaxCategory>
            </cac:TaxSubtotal>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Description><?= $escape($line['description']) ?></cbc:Description>
            <cac:SellersItemIdentification><cbc:ID><?= $escape($line['code']) ?></cbc:ID></cac:SellersItemIdentification>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="<?= $escape($currency) ?>"><?= $money($line['unit_price']) ?></cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
<?php endforeach; ?>
</Invoice>
