import {
  reactExtension,
  useSettings,
  useApi,
  BlockStack,
  Text,
  Button,
  ChoiceList,
  Choice,
  Select,
  TextField,
  Divider,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useMemo, useState } from 'react';

const BUILD_ID = 'zyg-surveys-thankyou-20260212';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

export default reactExtension('purchase.thank-you.block.render', () => <ThankYouSurvey />);

function ThankYouSurvey() {
  const settings = useSettings();
  const api = useApi();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || '').trim();
  const runtimeShop = (typeof api?.shop?.myshopifyDomain === 'string' && api.shop.myshopifyDomain) ? api.shop.myshopifyDomain : null;
  const shopDomain = runtimeShop || '';
  const showDebugWhenEmpty = getSetting(settings, 'show_debug_when_empty') === true;

  const [loading, setLoading] = useState(true);
  const [survey, setSurvey] = useState(null);
  const [status, setStatus] = useState({ type: 'idle', message: '' });
  const [answers, setAnswers] = useState({});
  const [submitted, setSubmitted] = useState(false);
  const [reward, setReward] = useState(null);

  const orderId =
    api?.orderConfirmation?.current?.id ??
    api?.orderConfirmation?.id ??
    api?.order?.id ??
    null;

  useEffect(() => {
    if (!apiUrl || !secret || !shopDomain) {
      setLoading(false);
      setSurvey(null);
      setStatus({ type: 'not_configured', message: 'Not configured' });
      return;
    }
    setLoading(true);
    setSubmitted(false);
    setReward(null);
    setAnswers({});

    fetch(`${apiUrl}/api/surveys/active`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Extension-Secret': secret,
        Accept: 'application/json',
      },
      body: JSON.stringify({
        shop: shopDomain,
        surface: 'thank_you',
        order_id: orderId,
      }),
    })
      .then((r) => (r.ok ? r.json() : { survey: null }))
      .then((data) => {
        setSurvey(data?.survey ?? null);
        setStatus({ type: 'ok', message: data?.survey ? 'Survey loaded' : 'No survey' });
      })
      .catch(() => {
        setSurvey(null);
        setStatus({ type: 'error', message: 'Connection error' });
      })
      .finally(() => setLoading(false));
  }, [apiUrl, secret, shopDomain, String(orderId || '')]);

  const ui = survey?.ui && typeof survey.ui === 'object' ? survey.ui : {};
  const questions = Array.isArray(survey?.questions) ? survey.questions : [];

  const requiredMissing = useMemo(() => {
    for (let i = 0; i < questions.length; i += 1) {
      const q = questions[i] || {};
      const key = `q${i}`;
      const v = answers[key];
      if (q.required === false) continue;
      if (q.type === 'multi_choice') {
        if (!Array.isArray(v) || v.length === 0) return true;
      } else if (v === undefined || v === null || String(v).trim() === '') {
        return true;
      }
    }
    return false;
  }, [questions, answers]);

  const submit = async () => {
    if (!survey || !survey.id) return;
    const payloadAnswers = questions.map((q, i) => ({
      key: `q${i}`,
      type: q.type,
      prompt: q.prompt,
      value: answers[`q${i}`],
    }));
    try {
      const res = await fetch(`${apiUrl}/api/surveys/respond`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Extension-Secret': secret,
          Accept: 'application/json',
        },
        body: JSON.stringify({
          shop: shopDomain,
          surface: 'thank_you',
          order_id: orderId,
          survey_id: survey.id,
          answers: payloadAnswers,
        }),
      });
      const data = await res.json();
      if (res.ok && data?.ok) {
        setSubmitted(true);
        setReward(data.reward || survey.reward || null);
      } else {
        setStatus({ type: 'error', message: data?.error || 'Submit failed' });
      }
    } catch (_) {
      setStatus({ type: 'error', message: 'Submit failed' });
    }
  };

  const debugBlock = (
    <BlockStack spacing="extraTight">
      <Text size="small" appearance="subdued">Customer Surveys · {BUILD_ID}</Text>
      <Text size="small" appearance="subdued">Status: {status.type} {status.message ? `— ${status.message}` : ''}</Text>
    </BlockStack>
  );

  if (loading) {
    return <BlockStack spacing="tight">{showDebugWhenEmpty ? debugBlock : <Text appearance="subdued" size="small">Loading…</Text>}</BlockStack>;
  }

  if (!survey) {
    return showDebugWhenEmpty ? <BlockStack spacing="tight">{debugBlock}</BlockStack> : <BlockStack spacing="none" />;
  }

  if (submitted) {
    const rw = reward || survey.reward || {};
    return (
      <BlockStack spacing="tight">
        <Text size="medium" emphasis="bold">{ui.thanks_title || 'Thanks!'}</Text>
        {ui.thanks_body ? <Text appearance="subdued">{ui.thanks_body}</Text> : null}
        {rw?.type === 'code' && rw?.code ? (
          <BlockStack spacing="extraTight">
            <Divider />
            <Text emphasis="bold">Your code: {rw.code}</Text>
            <Text appearance="subdued" size="small">{rw.message || 'Copy this code and apply it in the Discount code field.'}</Text>
          </BlockStack>
        ) : null}
      </BlockStack>
    );
  }

  return (
    <BlockStack spacing="loose">
      <Text size="medium" emphasis="bold">{ui.title || survey.name || 'Quick question'}</Text>
      {ui.description ? <Text appearance="subdued">{ui.description}</Text> : null}
      {questions.map((q, i) => {
        const key = `q${i}`;
        const type = q?.type || 'single_choice';
        const prompt = q?.prompt || `Question ${i + 1}`;
        const opts = Array.isArray(q?.options) ? q.options : [];
        const value = answers[key];
        if (type === 'text') {
          return (
            <TextField
              key={key}
              label={prompt}
              value={value ?? ''}
              onChange={(v) => setAnswers((prev) => ({ ...prev, [key]: v }))}
              placeholder={q?.placeholder || ''}
            />
          );
        }
        if (type === 'select') {
          const options = opts.map((o) => ({ value: String(o.value ?? o.label ?? ''), label: String(o.label ?? o.value ?? '') }));
          return (
            <Select
              key={key}
              label={prompt}
              value={value ?? ''}
              options={options}
              onChange={(v) => setAnswers((prev) => ({ ...prev, [key]: v }))}
            />
          );
        }
        if (type === 'multi_choice') {
          const values = Array.isArray(value) ? value : [];
          return (
            <ChoiceList
              key={key}
              name={key}
              value={values}
              onChange={(v) => setAnswers((prev) => ({ ...prev, [key]: v }))}
            >
              <BlockStack spacing="tight">
                <Text emphasis="bold">{prompt}</Text>
                {opts.map((o, idx) => (
                  <Choice key={`${key}_${idx}`} id={String(o.value ?? o.label ?? idx)}>
                    {String(o.label ?? o.value ?? '')}
                  </Choice>
                ))}
              </BlockStack>
            </ChoiceList>
          );
        }
        return (
          <ChoiceList
            key={key}
            name={key}
            value={String(value ?? '')}
            onChange={(v) => setAnswers((prev) => ({ ...prev, [key]: v }))}
          >
            <BlockStack spacing="tight">
              <Text emphasis="bold">{prompt}</Text>
              {opts.map((o, idx) => (
                <Choice key={`${key}_${idx}`} id={String(o.value ?? o.label ?? idx)}>
                  {String(o.label ?? o.value ?? '')}
                </Choice>
              ))}
            </BlockStack>
          </ChoiceList>
        );
      })}

      <Button kind="primary" disabled={requiredMissing} onPress={submit}>
        {ui.submit_label || 'Submit'}
      </Button>

      {showDebugWhenEmpty ? debugBlock : null}
    </BlockStack>
  );
}

